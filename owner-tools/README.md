# DISI License Approval Tool

The private key in this folder approves one WordPress installation at a time.
It is intentionally excluded from Git and from the plugin ZIP.

1. Ask the site administrator to open `DISI Portal > License`.
2. Copy the complete `DISI-REQ-...` request code.
3. From the repository root, run:

```bash
php owner-tools/generate-license.php 'DISI-REQ-...'
```

4. Send the generated `DISI-LIC-...` activation key to the site
   administrator.

Keep `disi-license-private.pem` private and backed up securely. Losing it
prevents new approvals; sharing it allows another person to issue approvals.
