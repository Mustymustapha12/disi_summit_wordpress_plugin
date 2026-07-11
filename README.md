# DISI Summit Portal

WordPress plugin for DISI Summit 2026 registration capture, review, pricing,
payment notifications, and form-provider integration.

## Latest Release

Version `0.5.5`

Installable package:
`outputs/disi-summit-portal-v0.5.5.zip`

## Source

The editable plugin source is in:
`work/DISI Summit Portal V0.1.1`

The internal folder name remains stable so WordPress replaces the installed
plugin during ZIP uploads instead of creating a second plugin.

## Site Approval

Version `0.5.5` requires a signed approval key for each WordPress
installation. The owner-only generator is documented in
`owner-tools/README.md`. Its private signing key is excluded from Git and from
the installable plugin package.

## Version 0.5.5

- Fixes repeated WordPress upgrade warnings by replacing repeated `dbDelta()`
  calls with direct `CREATE TABLE IF NOT EXISTS` table creation.
- Fixes Paystack paid transactions being rejected because Paystack returned
  metadata or customer email differently from the stored registration.
- Verification now keeps the critical reference, amount, currency, and live/test
  mode checks, and reports the exact mismatch if any critical value fails.
- Adds a Paystack webhook endpoint:
  `/wp-admin/admin-post.php?action=disi_paystack_webhook`
  with Paystack signature verification.

## Version 0.5.4

- Adds a VIP Amount field on the Integration page.
- Detects VIP registrations from supported form submissions.
- Includes VIP in registration and E-ticketing type filters.
- Adds Exhibition Payment Amount as an add-on like Workshop.
- Adds the `manage_disi_portal` capability for role editor plugins.
- Adds a separate Group Booking Form selector on the Integration page.
- Group Booking tickets now show the admitted number of people and key group booking fields, with attendee names title-cased and wrapped onto separate lines.
- Adds memorable backup license keys requested by the plugin owner.
- Adds Sponsorship Enquiries form assignment, capture, listing, email, and call actions.
- Adds Duplicate Entries queue for blocked duplicate email/phone submissions, with guarded approve/reject actions.
