## Two different jobs

A project can fail in two ways. The first is that nobody can operate it: the repository, domain, hosting, broker or customer data are locked behind one person. The second is that nobody wants to operate it: the maintainer has gone, and no successor has been found.

Relay is for the second problem. A handover mechanism is for the first. They are different layers, and a project needs both.

## What a handover mechanism does

A handover mechanism is a prepared route that works without the maintainer. It does not transfer ownership by itself. It points the running system at a successor site that has already been prepared, under a procedure the maintainer can cancel.

A useful pattern has eight parts:

1. **A sealed secret.** A strong passphrase is generated, stored in the project's own host settings, and sealed in an envelope or password manager for the successor. It never goes in the repository or in any file the site serves.
2. **A prepared new site.** The successor site is a working deployment of the same code, with the data, configuration, TLS certificate and broker details it needs. It is not a placeholder.
3. **A request page.** A public, no-index page accepts the passphrase and the address of the prepared site. It explains that nothing changes immediately, that the maintainer will be notified, and that the request can be cancelled.
4. **A notification.** The maintainer is emailed and, if the project has an app, sees an alert there. The notification says who asked, from where, for which site, and when the change would take effect. It does not contain the passphrase.
5. **A waiting period.** Nothing changes for a fixed number of days. RBR uses 14. The period is short enough to be useful in an emergency and long enough for the maintainer to notice and cancel a false request.
6. **A cancellation route.** The maintainer can cancel from the app or from a signed link in the email. Cancellation must work without the passphrase and without the successor.
7. **A readiness check.** When the waiting period ends, the new site is checked: TLS, the update endpoint, the credentials endpoint, and whatever else the running system needs. A failed check leaves the old site in place.
8. **A switch.** The running system learns about the change on its next update check and moves its update address. If the new site names a different broker, the bridge moves too.

The order matters. The secret gets you in; the waiting period stops a false request; the readiness check stops a broken new site; the switch is the only thing that changes the running system.

## The RBR example

Room By Room is a heating system that depends on a website for updates and a broker for remote control. Its handover channel is implemented, documented and tested.

- **The page.** `/handover/` asks for the passphrase from the sealed envelope and the address of a prepared new site. It is no-index and explains the three things a person needs to know: nothing changes immediately, the maintainer is notified, and the new site is checked.
- **The secret.** The passphrase lives in the host settings above the web root and in sealed envelopes. It is compared with `hash_equals`, never stored in a request record and never echoed. An empty or missing passphrase disables the channel.
- **The request.** A valid request is recorded in a file above the web root, under an exclusive lock. Only one request can be pending or active at a time.
- **The notification.** The maintainer is emailed and sees a flashing alert in the app. The email includes the requester's address and user agent, the new site, the effective date and a signed cancellation link. It does not include the passphrase.
- **The waiting period.** Nothing changes for 14 days. The maintainer can cancel from the app or the email link. A short illness, a holiday or a busy month is not a handover.
- **The readiness check.** When the period ends, the new site must pass a check: TLS, a valid update endpoint and a working credentials endpoint. A failed check leaves the old site in place.
- **The switch.** Each controller learns about the change on its next hourly update check and moves its update address, and its MQTT bridge if the new site names a different broker.

The channel is a mechanism, not an authority. It does not satisfy RBR's 30-day continuity trigger, appoint a successor, transfer the domain or hosting, or move customer data. Those are separate human and legal steps in the project's continuity plan.

## What it does not do

- It does not transfer the domain, hosting, broker, mail account or repository.
- It does not appoint a successor or change a legal position.
- It does not migrate customer data between sites.
- It does not keep the old site alive. The old site is the only thing that can tell the running system to move; if the domain lapses, the channel goes with it.
- It is not a substitute for a named successor and a written continuity plan.

## What has to be prepared

A handover mechanism is cheap to design and easy to postpone. The work is in the preparation:

- Generate a strong passphrase and put it in the host settings above the web root and in a sealed envelope for the successor.
- Prepare the new site before it is needed: the same code, a copy of the customer register and host settings, valid TLS, the update endpoint, the broker and a tested credentials endpoint.
- Decide the waiting period. RBR uses 14 days; its continuity plan uses a separate 30-day trigger. The shorter period protects a temporarily absent maintainer; the longer one governs the slower account transfers.
- Test the page against a staging site, including a cancellation. A sealed envelope nobody has opened is not a tested channel.
- Test the readiness check and the switch with a real client, not just a browser.
- Keep the old site reachable.
- Review the mechanism whenever the site layout, update endpoint or broker changes.
- Rotate credentials after a handover. Assume the old ones are exposed.

## Security notes

- The passphrase is the only guard on the public request form. Rate-limit wrong attempts per address and add a small delay.
- Keep the passphrase out of the repository, out of any served file and out of notification emails.
- Store request state outside the web root, under an exclusive lock, without the passphrase.
- The readiness check is an outbound request driven by form input. Refuse private addresses, follow no redirects, bound the body and verify TLS.
- A waiting period is a security feature. It gives the maintainer time to notice and cancel a false or malicious request.
- A handover that has never been exercised is a design, not a capability.

## If you hold a sealed passphrase

Use it only through the project's own handover procedure. Do not send it to Relay, paste it into a listing, or include it in an email to a stranger. Relay does not ask for a passphrase, cannot use one, and is not a replacement site. Its job is to help you find the person who should hold it next.

## Recommendation

Every project with a live service should have three things: a written continuity plan, a named backup maintainer, and a handover mechanism that has been tested. The first two are about authority and people. The third is about the system continuing to run while the slower transfers happen.

Use the sealed-passphrase pattern. Make the new site real before the emergency. Write down who may invoke the mechanism and what evidence they need. Then test it, cancel it, and test it again.

RBR's full description is in its [continuity plan](https://github.com/easycoder/rbr/blob/main/CONTINUITY.md) and [handover channel](https://github.com/easycoder/rbr/blob/main/doc/HANDOVER.md). Relay's own continuity plan is next; this site should be the first project in its own catalogue with a tested handover, not the exception.
