# Cancel, interest relay and owner previews — what changed and what to do

**Action:** apply `db/migrations/0002_add_project_revisions.sql` and `db/migrations/0003_add_interest_details.sql` if they are not already applied, then run `./deploy.sh --build`. Open an approved listing as a non-owner and use **Express interest**; the owner accepts at `/interests/`.

## What changed

- The edit form has **Cancel** beside Save.
- Approved listings have **Express interest** → `/interest/?project=…`. A signed-in applicant answers four short questions; their email is hidden from the owner.
- The owner uses `/interests/` (linked from the dashboard and each project's “new expression” badge) to read, accept or decline. Accepting reveals both email addresses, sends an introduction to both and audits the decision; declining hides them and shows the reason to the applicant.
- The applicant's dashboard lists expressions and statuses; accepted ones show the owner's email.
- Earlier in this change: dashboard previews, header name/email, editing and sign-up names.

## Notes

- Apply `0003` before deploying; `/interest/`, `/interests/` and the dashboard query the new columns.
- The pending dummy is not open for interest until it is approved and rebuilt. Use the Relay listing to test the relay, or approve the dummy first.
- `mail()` delivery is used for the notification and introduction; contact details also appear on the pages after acceptance, so a failed email does not block the introduction.
