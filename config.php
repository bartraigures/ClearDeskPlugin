<?php
/*
 * Clear Desk notifications: settings shown in the osTicket admin panel.
 * Copyright (C) 2026 Silas Slack. GNU General Public License version 2; see LICENSE.
 */
require_once INCLUDE_DIR . 'class.plugin.php';

class ClearDeskConfig extends PluginConfig {

    const DEFAULT_RELAY = 'https://ng4e4cc4icqx5f45jn4ssql74e0jjcbn.lambda-url.eu-west-1.on.aws/v1/notify';

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
            'notify_new' => new BooleanField(array(
                'label' => 'New tickets',
                'default' => true,
                'configuration' => array('desc' => 'The assigned agent, or everyone with access to the department if nobody is assigned'),
            )),
            'notify_reply' => new BooleanField(array(
                'label' => 'Customer replies',
                'default' => true,
                'configuration' => array('desc' => 'The assigned agent, or everyone with access to the department if nobody is assigned'),
            )),
            'notify_assigned' => new BooleanField(array(
                'label' => 'Assignments',
                'default' => true,
                'configuration' => array('desc' => 'The agent a ticket is assigned to, when someone else assigns it'),
            )),
            'notify_note' => new BooleanField(array(
                'label' => 'Internal notes',
                'default' => false,
                'configuration' => array('desc' => 'The assigned agent, when a colleague adds a note'),
            )),
        );
    }
}
