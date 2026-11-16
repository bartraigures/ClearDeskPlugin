# Clear Desk notifications

An osTicket plugin that lets the Clear Desk app notify agents at once when something happens on a ticket.

Clear Desk is an independent iPhone and iPad app for osTicket agents: https://bartraigures.github.io/ClearDesk-app/

The app works without this plugin, but then it is only told about new activity when the agent opens it. With the plugin, a notification arrives within seconds.

**Status: early version 0.1, not yet released.** Expect changes. Do not rely on it in production yet.

## What it does

The plugin listens for four events:

- a new ticket;
- a customer reply;
- a ticket being assigned to an agent by someone else;
- an internal note from a colleague (off by default).

For each event it decides who to tell. That is the assigned agent. If the ticket is assigned to a team and not to a person, it is the members of that team. If nobody is assigned, it is every agent with access to the ticket's department. Only agents who have registered the app are told, and agents marked as on holiday are skipped. The person who caused the event is not told.

It then sends a small message to a delivery service. The delivery service passes it to Apple, and Apple shows the notification on the agent's device. Apple only accepts notifications from an app's publisher, which is why a delivery service is needed.

Agents register their device from the app after signing in. For each device the plugin stores the agent's id, Apple's notification address for that device (the device token), and which kinds of event the agent wants there. These are kept in one extra database table, `<prefix>cleardesk_device`, where `<prefix>` is your osTicket table prefix (usually `ost_`).

## What leaves the helpdesk

For each event, the plugin sends only:

- the kind of event: `new`, `reply`, `assigned` or `note`;
- the ticket's internal id and its ticket number;
- the device tokens of the agents to tell.

Nothing else is sent. No subject, no message text, no customer name or email address, no agent name, and no helpdesk address in the message body.

A complete message looks like this:

```json
{"v":1,"event":"reply","ticket":{"id":123,"number":"374502"},"devices":[{"token":"…","env":"production"}]}
```

The notification on the phone reads like "Ticket #374502 — The customer replied". When the agent opens it, the app reads the ticket from the helpdesk directly, using the agent's own sign-in.

What the delivery service and Apple can still infer: that a helpdesk at the sending IP address had an event on a ticket with that number at that time. If that is not acceptable for your organisation, do not install the plugin.

## Requirements

- Self-hosted osTicket 1.17 or 1.18. Checked on 1.17, 1.18.2 and 1.18.4.
- PHP with the curl extension.
- Outbound HTTPS from the server to the delivery service.

Hosted (SupportSystem) installs cannot add plugins and are not supported.

## Installing

Step-by-step instructions with every screen: https://bartraigures.github.io/ClearDesk-app/notifications.html

In short:

1. Copy this folder to `include/plugins/cleardesk` on the osTicket server.
2. In the Admin Panel, go to Manage → Plugins → Add New Plugin, and install "Clear Desk notifications".
3. On the page that opens, set **Status** to **Active** and press **Save Changes**.
4. Open the **Instances** tab, press **Add New Instance**, give it a name (for example "Clear Desk"), set **Status** to **Enabled** and press **Add Instance**. The **Config** tab holds the settings below; the defaults are fine.
5. Each agent then signs in to the Clear Desk app and switches notifications on in Settings.

## Settings

The instance has these settings:

- **Delivery service address.** Where messages are sent. Leave it as it is unless you run your own delivery service.
- **New tickets.** On or Off. On by default.
- **Customer replies.** On by default.
- **Assignments.** On by default.
- **Internal notes.** Off by default.

These switches apply to the whole helpdesk. Each agent can also choose, in the app, which of the allowed kinds they want on each of their devices; that choice is stored with the device registration.

An administrator can turn everything off at any time by disabling the plugin. The app carries on working without notifications.

## Troubleshooting

If nothing arrives:

- Check that the plugin is Active and its instance is Enabled.
- Check that the agent has switched notifications on in the app's Settings.
- Check that the server can reach the delivery service over HTTPS. A firewall or proxy may block it.

Errors are written to osTicket's system log (Admin Panel → Dashboard → System Logs).

## Removing it

1. In the Admin Panel, disable the plugin, then delete it.
2. Remove the `include/plugins/cleardesk` folder from the server.
3. If you want to remove the stored device registrations as well, drop the table:

```sql
DROP TABLE ost_cleardesk_device;
```

Replace `ost_` with your table prefix if it is different.

## Licence

This plugin is free software, released under the GNU General Public License version 2. See the `LICENSE` file.

Source: https://github.com/bartraigures/ClearDeskPlugin

## No affiliation

Clear Desk is an independent project. It is not affiliated with, endorsed by or supported by Enhancesoft or the osTicket project. osTicket is a trademark of Enhancesoft LLC.
