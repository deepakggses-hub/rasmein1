# Rasmein — scheduled tasks

Two commands need to run on a schedule. Without the first, no email ever
leaves the system.

```cron
# Drain the outbound email queue — every five minutes.
*/5 * * * * cd /var/www/rasmein && /usr/bin/php spark rasmein:send-mail >> writable/logs/cron-mail.log 2>&1

# Low-stock alerts, abandoned carts, pruning old rows — daily at 02:30.
30 2 * * * cd /var/www/rasmein && /usr/bin/php spark rasmein:housekeeping >> writable/logs/cron-housekeeping.log 2>&1
```

Adjust the paths and the PHP binary to the server. On shared hosting the cron
panel usually wants the same two lines without the `cd`, using an absolute path
to `spark`.

## Checking it works

```bash
php spark rasmein:send-mail            # sends what is due, reports counts
php spark rasmein:housekeeping         # safe to run by hand any time
php spark rasmein:diag                 # preflight: PHP, extensions, .env, DB
```

A queued email that has failed five times is left as `failed` in
`notification_log` with the reason in `error`, rather than retried forever.

## SMTP

**Configure this in the admin panel: Settings → Mail.** All three sending
methods live on one screen, one active at a time, with a "send a test to me"
button that reports the real server error when it fails.

The SMTP password is stored encrypted, so `php spark key:generate` must have
been run first — the panel refuses to store a password without a key rather than
keeping it in plain text.

`.env` still works as a fallback for anything the panel leaves blank:

```
email.protocol  = 'smtp'
email.SMTPHost  =
email.SMTPUser  =
email.SMTPPass  =
email.SMTPPort  = 587
email.SMTPCrypto = 'tls'
email.fromEmail = 'orders@rasmein.com'
email.fromName  = 'Rasmein'
```

Then use **Admin → Email templates → Send test** to confirm delivery. The test
only ever goes to the signed-in administrator's own address.


## Sending through a Google / Gmail account

**Settings → Mail → Google / Gmail.** This uses Gmail's API with OAuth, so no
password is involved and app-specific passwords are unnecessary.

1. `console.cloud.google.com` → create a project.
2. Enable the **Gmail API**.
3. Configure the OAuth consent screen. While it is in **Testing**, add the
   sending address under **Test users** — Google refuses the authorisation
   otherwise.
4. Create an **OAuth client ID**, type *Web application*.
5. Add the authorised redirect URI shown on the mail settings screen. It must
   match exactly, including http vs https and any port — it is derived from
   `app.baseURL`, so that must be correct.
6. Paste the client ID and secret, save, then press **Authorise a Google
   account** and complete the consent screen.
7. Send a test.

`php spark key:generate` must have been run first: the client secret and refresh
token are stored encrypted, and the panel refuses to store them otherwise.

Only the `gmail.send` permission is requested. Revoke it any time at
`myaccount.google.com/permissions`.

**Note:** Google sends as the authorised account, whatever the from address says.
