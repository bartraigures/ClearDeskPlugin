<?php
/*
 * Clear Desk notifications: an osTicket plugin.
 * Copyright (C) 2026 Silas Slack
 *
 * This program is free software; you can redistribute it and/or modify it under the terms
 * of the GNU General Public License version 2 as published by the Free Software Foundation.
 * It is distributed WITHOUT ANY WARRANTY. See the LICENSE file for details.
 *
 * What it does: when a ticket arrives, a customer replies or a ticket is assigned, it tells
 * the agents concerned through the Clear Desk app. It sends a delivery service the kind of
 * event, the ticket number and the agents' notification addresses, and nothing else.
 */
require_once INCLUDE_DIR . 'class.plugin.php';
require_once INCLUDE_DIR . 'class.signal.php';
require_once 'config.php';

define('CLEARDESK_DEVICE_TABLE', TABLE_PREFIX . 'cleardesk_device');

class ClearDeskPlugin extends Plugin {
    var $config_class = 'ClearDeskConfig';

    const VERSION = '0.1';

    static private $started = false;

    function bootstrap() {
        // One set of listeners however many instances are enabled; the first enabled one's settings apply.
        if (self::$started)
            return;
        self::$started = true;

        $config = $this->getConfig();
        ClearDeskNotifier::configure(array(
            'relay_url' => trim((string) $config->get('relay_url')),
            'new' => $config->isOn('notify_new'),
            'reply' => $config->isOn('notify_reply'),
            'assigned' => $config->isOn('notify_assigned'),
            'note' => $config->isOn('notify_note'),
        ));

        Signal::connect('ticket.created', array('ClearDeskNotifier', 'onTicketCreated'));
        Signal::connect('threadentry.created', array('ClearDeskNotifier', 'onThreadEntry'));
        Signal::connect('object.edited', array('ClearDeskNotifier', 'onObjectEdited'));

        // The app's own endpoints, under the agent panel so that the agent's sign-in protects them.
        Signal::connect('ajax.scp', function ($dispatcher) {
            $api = new ClearDeskDeviceAPI();
            $dispatcher->append(url_get('^/cleardesk/info$', array($api, 'info')));
            $dispatcher->append(url_post('^/cleardesk/devices$', array($api, 'register')));
            $dispatcher->append(url_post('^/cleardesk/devices/remove$', array($api, 'remove')));
        });
    }
}

/** The devices agents have registered from the app: one row per device. */
class ClearDeskDevices {
    static private $ready = false;

    static function ensureTable() {
        if (self::$ready)
            return;
        db_query('CREATE TABLE IF NOT EXISTS `' . CLEARDESK_DEVICE_TABLE . '` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `staff_id` int(11) unsigned NOT NULL,
            `token` varchar(200) NOT NULL,
            `environment` varchar(16) NOT NULL DEFAULT \'production\',
            `events` varchar(64) NOT NULL DEFAULT \'new,reply,assigned\',
            `created` datetime NOT NULL,
            `updated` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `token` (`token`),
            KEY `staff_id` (`staff_id`)
        ) DEFAULT CHARSET=utf8');
        self::$ready = true;
    }

    static function validToken($token) {
        return is_string($token) && preg_match('/^[0-9a-fA-F]{64,200}$/', $token);
    }

    const EVENTS = 'new,reply,assigned,note';
    const DEFAULT_EVENTS = 'new,reply,assigned';

    /** The agent's own choice of events for a device, as a clean comma list; null if not given. */
    static function cleanEvents($text) {
        if (!is_string($text))
            return null;
        return implode(',', array_values(array_intersect(explode(',', self::EVENTS), array_map('trim', explode(',', $text)))));
    }

    /** A device belongs to whoever registered it last. `$events` is the agent's choice for this device. */
    static function save($staffId, $token, $environment, $events = null) {
        self::ensureTable();
        $environment = $environment === 'sandbox' ? 'sandbox' : 'production';
        if ($events === null)
            $events = self::DEFAULT_EVENTS;
        return db_query('INSERT INTO `' . CLEARDESK_DEVICE_TABLE . '` SET staff_id=' . db_input((int) $staffId)
            . ', token=' . db_input(strtolower($token)) . ', environment=' . db_input($environment)
            . ', events=' . db_input($events) . ', created=NOW(), updated=NOW()'
            . ' ON DUPLICATE KEY UPDATE staff_id=VALUES(staff_id), environment=VALUES(environment), events=VALUES(events), updated=NOW()');
    }

    static function remove($token) {
        self::ensureTable();
        return db_query('DELETE FROM `' . CLEARDESK_DEVICE_TABLE . '` WHERE token=' . db_input(strtolower($token)));
    }

    /** staff id => list of array(token, env), for the devices that want this kind of event */
    static function byStaff($event = null) {
        self::ensureTable();
        $out = array();
        $res = db_query('SELECT staff_id, token, environment, events FROM `' . CLEARDESK_DEVICE_TABLE . '`');
        while ($res && ($row = db_fetch_array($res))) {
            if ($event !== null && !in_array($event, explode(',', $row['events'])))
                continue;
            $out[(int) $row['staff_id']][] = array('token' => $row['token'], 'env' => $row['environment']);
        }
        return $out;
    }
}

/** Endpoints the app calls while signed in as an agent: ajax.php/cleardesk/... */
class ClearDeskDeviceAPI {

    private function reply($code, $data) {
        Http::response($code, json_encode($data), 'application/json');
    }

    /** Lets the app see that the plugin is installed and what it will notify about. */
    function info() {
        global $thisstaff;
        if (!$thisstaff)
            return $this->reply(403, array('error' => 'Sign in first'));
        $this->reply(200, array(
            'plugin' => 'cleardesk',
            'version' => ClearDeskPlugin::VERSION,
            'events' => ClearDeskNotifier::enabledEvents(),
        ));
    }

    function register() {
        global $thisstaff;
        if (!$thisstaff)
            return $this->reply(403, array('error' => 'Sign in first'));
        $token = isset($_POST['token']) ? $_POST['token'] : '';
        if (!ClearDeskDevices::validToken($token))
            return $this->reply(400, array('error' => 'Invalid device token'));
        $environment = isset($_POST['environment']) ? $_POST['environment'] : 'production';
        // Which kinds of event this agent wants on this device, e.g. "new,reply". Left out: the usual three.
        $events = ClearDeskDevices::cleanEvents(isset($_POST['events']) ? $_POST['events'] : null);
        if (!ClearDeskDevices::save($thisstaff->getId(), $token, $environment, $events))
            return $this->reply(500, array('error' => 'Could not save the device'));
        $this->reply(200, array('registered' => true,
            'events' => $events === null ? ClearDeskDevices::DEFAULT_EVENTS : $events));
    }

    function remove() {
        global $thisstaff;
        if (!$thisstaff)
            return $this->reply(403, array('error' => 'Sign in first'));
        $token = isset($_POST['token']) ? $_POST['token'] : '';
        if (!ClearDeskDevices::validToken($token))
            return $this->reply(400, array('error' => 'Invalid device token'));
        ClearDeskDevices::remove($token);
        $this->reply(200, array('registered' => false));
    }
}

class ClearDeskNotifier {
    static private $settings = array();
    /** Tickets created during this request: their first message is not also announced as a reply. */
    static private $justCreated = array();

    static function configure($settings) {
        self::$settings = $settings;
    }

    static function enabledEvents() {
        $out = array();
        foreach (array('new', 'reply', 'assigned', 'note') as $event)
            $out[$event] = !empty(self::$settings[$event]);
        return $out;
    }

    // -- osTicket events ---------------------------------------------------------------

    static function onTicketCreated($ticket) {
        if (!$ticket instanceof Ticket)
            return;
        self::$justCreated[$ticket->getId()] = true;
        if (!empty(self::$settings['new']))
            self::notify('new', $ticket, self::agentsFor($ticket));
    }

    static function onThreadEntry($entry) {
        if (!$entry instanceof ThreadEntry || !($thread = $entry->getThread()))
            return;
        $ticket = $thread->getObject();
        if (!$ticket instanceof Ticket)
            return;

        if ($entry instanceof MessageThreadEntry) {
            // A ticket's opening message arrives before "ticket.created"; that event covers it.
            if (empty(self::$settings['reply']) || isset(self::$justCreated[$ticket->getId()])
                    || $thread->getNumEntries() <= 1)
                return;
            self::notify('reply', $ticket, self::agentsFor($ticket));
        }
        elseif ($entry instanceof NoteThreadEntry) {
            // A colleague's note, to the agent who owns the ticket.
            $owner = (int) $ticket->getStaffId();
            if (empty(self::$settings['note']) || !$owner || !$entry->getStaffId()
                    || $owner == (int) $entry->getStaffId())
                return;
            self::notify('note', $ticket, array($owner));
        }
    }

    static function onObjectEdited($object, $type = null) {
        if (!$object instanceof Ticket || !is_array($type) || !isset($type['type'])
                || $type['type'] != 'assigned' || !empty($type['claim']) || empty(self::$settings['assigned']))
            return;
        // Team assignments send the same event; only an agent assignment has someone to tell.
        if (($owner = (int) $object->getStaffId()) && !isset(self::$justCreated[$object->getId()]))
            self::notify('assigned', $object, array($owner));
    }

    // -- Who to tell -------------------------------------------------------------------

    /**
     * The assigned agent; otherwise the members of the assigned team; otherwise every agent
     * with access to the ticket's department. Only agents who registered a device count.
     */
    static private function agentsFor($ticket) {
        if ($owner = (int) $ticket->getStaffId())
            return array($owner);
        $team = $ticket->getTeamId() ? $ticket->getTeam() : null;
        $dept = $ticket->getDept();
        $out = array();
        foreach (array_keys(ClearDeskDevices::byStaff()) as $staffId) {
            if (!($staff = Staff::lookup($staffId)) || !$staff->isActive() || $staff->onVacation())
                continue;
            if ($team ? $team->hasMember($staff) : $staff->canAccessDept($dept))
                $out[] = $staffId;
        }
        return $out;
    }

    // -- Sending -----------------------------------------------------------------------

    static private function notify($event, $ticket, $staffIds) {
        global $thisstaff;
        $relay = isset(self::$settings['relay_url']) ? self::$settings['relay_url'] : '';
        if (!$staffIds || !preg_match('#^https?://#i', $relay))
            return;

        $devices = array();
        $registered = ClearDeskDevices::byStaff($event);
        foreach (array_unique(array_map('intval', $staffIds)) as $staffId) {
            // Whoever did it does not need telling.
            if ($thisstaff && $thisstaff->getId() == $staffId)
                continue;
            if (isset($registered[$staffId]))
                $devices = array_merge($devices, $registered[$staffId]);
        }
        if (!$devices)
            return;

        // This is everything that leaves the helpdesk: no subject, text, names or addresses.
        foreach (array_chunk($devices, 100) as $batch)
            self::post($relay, array(
                'v' => 1,
                'event' => $event,
                'ticket' => array('id' => (int) $ticket->getId(), 'number' => (string) $ticket->getNumber()),
                'devices' => $batch,
            ));
    }

    static private function post($url, $payload) {
        global $ost;
        if (!function_exists('curl_init'))
            return;
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
            CURLOPT_RETURNTRANSFER => true,
            // Never hold up the helpdesk for long if the delivery service is slow or down.
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_USERAGENT => 'ClearDeskPlugin/' . ClearDeskPlugin::VERSION,
        ));
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($status != 200) {
            if ($ost)
                $ost->logWarning('Clear Desk notifications',
                    sprintf('The delivery service did not accept a notification (%s).', $error ?: 'HTTP ' . $status), false);
            return;
        }
        // Devices Apple no longer knows (app deleted, notifications switched off) are forgotten.
        $reply = json_decode($body, true);
        if (is_array($reply) && !empty($reply['invalid']) && is_array($reply['invalid']))
            foreach ($reply['invalid'] as $token)
                if (ClearDeskDevices::validToken($token))
                    ClearDeskDevices::remove($token);
    }
}
