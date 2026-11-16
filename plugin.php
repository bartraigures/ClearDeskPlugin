<?php
/*
 * Clear Desk notifications: an osTicket plugin.
 * Copyright (C) 2026 Silas Slack
 *
 * This program is free software; you can redistribute it and/or modify it under the terms
 * of the GNU General Public License version 2 as published by the Free Software Foundation.
 * It is distributed WITHOUT ANY WARRANTY. See the LICENSE file for details.
 */
return array(
    'id' =>          'cleardesk:notify',
    'version' =>     '0.1',
    'ost_version' => '1.17',
    'name' =>        'Clear Desk notifications',
    'author' =>      'Silas Slack',
    'description' => 'Tells the Clear Desk iPhone and iPad app at once when a ticket arrives, a customer replies or a ticket is assigned. No ticket content leaves the helpdesk.',
    'url' =>         'https://github.com/bartraigures/ClearDeskPlugin',
    'plugin' =>      'cleardesk.php:ClearDeskPlugin',
);
