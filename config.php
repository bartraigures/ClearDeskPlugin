<?php
/*
 * Clear Desk notifications: settings shown in the osTicket admin panel.
 * Copyright (C) 2026 Silas Slack. GNU General Public License version 2; see LICENSE.
 */
require_once INCLUDE_DIR . 'class.plugin.php';

class ClearDeskConfig extends PluginConfig {

    const DEFAULT_RELAY = 'https://ng4e4cc4icqx5f45jn4ssql74e0jjcbn.lambda-url.eu-west-1.on.aws/v1/notify';

    /**
     * Whether a kind of event is switched on. These are On/Off lists, not tick boxes: osTicket's
     * "add instance" form loses tick boxes that start ticked, which silently saved everything as off.
     * osTicket stores a list choice as text or as JSON, depending on the version.
     */
    function isOn($key) {
        $value = $this->get($key);
        if (is_string($value) && $value !== '' && $value[0] == '{')
            $value = json_decode($value, true);
        if (is_array($value))
            $value = key($value);
        return $value === 'on' || $value === '1' || $value === 1 || $value === true;
    }

    function getOptions() {
        return array(
            'about' => new SectionBreakField(array(
                'label' => 'What is sent',
                'hint' => 'When something happens on a ticket, this plugin sends the delivery service below '
                    . 'three things: the kind of event, the ticket\'s number and internal id, and the notification addresses of the '
                    . 'agents to tell. No subject, message, customer name or email address is sent. '
                    . 'The app then reads the ticket from this helpdesk itself.',
            )),
            'relay_url' => new TextboxField(array(
                'label' => 'Delivery service address',
                'hint' => 'Apple only accepts notifications from the app\'s publisher, so they go through this service. Leave as it is unless you run your own.',
                'default' => self::DEFAULT_RELAY,
                'required' => true,
                'configuration' => array('size' => 60, 'length' => 255),
            )),
            'events' => new SectionBreakField(array('label' => 'Tell agents about')),
            'notify_new' => new ChoiceField(array(
                'label' => 'New tickets',
                'hint' => 'The assigned agent, or everyone with access to the department if nobody is assigned',
                'default' => 'on',
                'required' => true,
                'choices' => array('on' => 'On', 'off' => 'Off'),
            )),
            'notify_reply' => new ChoiceField(array(
                'label' => 'Customer replies',
                'hint' => 'The assigned agent, or everyone with access to the department if nobody is assigned',
                'default' => 'on',
                'required' => true,
                'choices' => array('on' => 'On', 'off' => 'Off'),
            )),
            'notify_assigned' => new ChoiceField(array(
                'label' => 'Assignments',
                'hint' => 'The agent a ticket is assigned to, when someone else assigns it',
                'default' => 'on',
                'required' => true,
                'choices' => array('on' => 'On', 'off' => 'Off'),
            )),
            'notify_note' => new ChoiceField(array(
                'label' => 'Internal notes',
                'hint' => 'The assigned agent, when a colleague adds a note',
                'default' => 'off',
                'required' => true,
                'choices' => array('on' => 'On', 'off' => 'Off'),
            )),
        );
    }
}
