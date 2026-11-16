# Relay — a proposal for relay.eclecity.net

> **Status:** agreed direction · **Date:** 2026-11-16 · **Name:** Relay · **Host:** relay.eclecity.net
>
> **Agreed on 2026-11-16:** the name **Relay**; listings and an interest relay first, with rooms in Phase 2; a pre-rendered public catalogue with a browser-based **AllSpeak** UI for the interactive surfaces, a **PHP** backend and a **database**; **free and open source**, with no paid tier.

## 1. The proposition

A place where the person who can no longer carry a project finds the person who can — and where the handover is documented well enough that an AI-assisted maintainer can take it on in days rather than months.

The site is a **registry, a search engine and an introduction service for projects that need a successor**. It is not a code marketplace, a broker, an escrow service or a handover authority. Its unit of value is not the listing; it is the **adoption-ready package**: a project whose purpose, economics, access and obligations are described clearly enough for a capable stranger to make an informed decision.

## 2. What it is, and what it is not

**It is:**

- a public, indexable catalogue of projects seeking a successor, co-maintainer or buyer;
- a structured way to describe a project so that a person — or their agent — can assess it quickly;
- a matching and messaging layer that lets an owner and an interested adopter talk without exposing either party to spam;
- a standard (`continuity.json` plus an **Adoption Brief**) that any project can adopt, with or without the site;
- a source of practical guidance on continuity, drawn from real cases such as RBR;
- free, open source and self-hostable, so that the directory does not become another project with no successor.

**It is not:**

- a place to sell code as an asset with no obligations attached;
- a guarantor that a handover will succeed, or that a project will keep running;
- a party to the transfer, a holder of funds, or a holder of credentials;
- a replacement for a project's own handover mechanism.

### The RBR connection

RBR's handover channel answers the question *"how does the system move if the maintainer disappears?"* It does not answer *"who takes it over, and how do they prepare?"* The new site is the missing layer above it:

| Layer | Question | Where it lives |
|---|---|---|
| Continuity plan | What has to move, and under what authority? | The project's own `CONTINUITY.md` |
| Handover mechanism | How do the controllers switch to a prepared site? | RBR's passphrase and 14-day channel |
| **Succession** | **Who is the next maintainer, and are they ready?** | **This site** |

The site should **never** ask for a passphrase, accept a handover request, or present itself as a replacement site. A dedicated page — *"If you hold a sealed passphrase"* — should point people back to the project's own process and explain why the two things are different. That distinction is a trust feature, not a footnote.

A companion guide should describe the mechanism itself — a sealed passphrase, a waiting period, a prepared successor site and a dry run — and recommend it as the continuity layer beneath the introduction. RBR is the worked example; the guide should be useful to a project that never lists here.

The RBR case is also the best content the site can launch with: a real project, a real plan, a real gap (no named successor yet), written honestly. It demonstrates the standard rather than asserting it.

## 3. The insight: continuity is a packaging problem

Most continuity efforts fail for one of three reasons: the owner cannot describe what they have, the adopter cannot assess it without weeks of reading, or nobody knows the project is available. AI changes the second one. An agent can read a repository, map its architecture, run its tests and produce a report in an afternoon. What it cannot do is grant access, transfer a domain, take on a customer obligation or decide whether the project is worth keeping alive.

So the site should optimise for the scarce things:

1. **Access** — who can actually hand over the repository, domain, hosting, data and accounts.
2. **Authority** — who is allowed to make the decision, and what evidence do they need?
3. **Context** — why the project exists, what it costs, who depends on it, what is unfinished.
4. **Trust** — that the owner is real, the listing is current, and the adopter is not a scavenger.

This leads to a deliberate bias: **fewer, better listings**. A directory of 500 abandoned repositories is worse than useless; it teaches visitors that the site is a graveyard. The launch target is 8–12 complete, honest, verified listings — not 100 thin ones.

## 4. Who it serves

The two audiences are asymmetric: there will always be more projects than adopters. The design should make the adopter's time cheap and the owner's obligations explicit.

### 4.1 The project owner

Typically a solo maintainer, often ageing into the role, who knows the project cannot depend on them forever. They may have customers, a small revenue stream, or none. They need to:

1. understand what "handover" actually involves;
2. assemble an Adoption Brief with help from an AI agent;
3. publish a listing that is honest about revenue, costs, obligations and gaps;
4. choose among interested adopters without exposing themselves to time-wasters;
5. transfer the assets and announce the change.

The site's job at each step is to reduce the blank-page problem: templates, prompts, checklists, examples and a readiness score.

### 4.2 The creator / adopter

Typically a programmer or AI-capable builder who wants a project with a visible purpose and, where possible, a revenue stream. They are not necessarily looking for a job; they may want a portfolio piece, a product to run, a mission to continue, or a customer base to serve. They need to:

1. search by domain, stack, effort, revenue and readiness;
2. get a machine-readable brief they can hand to an agent;
3. ask structured questions before committing;
4. talk to the owner in a private room;
5. understand what they are taking on — including the parts that are not in the repository.

> **Terminology.** You used "creators" for the incoming maintainer. I have used **adopter** in this document, because "creator" usually means the person who made the project. The UI can use whichever word tests better; "steward" is the other candidate.

## 5. The core artefact: the Adoption Brief

Every listing is a summary of a longer **Adoption Brief** — a Markdown document the owner writes (or drafts with an agent) and keeps in the repository. It is the thing an adopter's agent reads first. Suggested sections:

1. **What it is** — one paragraph a non-user would understand.
2. **Who uses it** — customers, installers, internal users, nobody yet.
3. **Why it exists** — the problem, the history, the thing that would be lost.
4. **How it works** — architecture in plain language, with a diagram and the three files that matter most.
5. **How to run, test and deploy it** — the commands, the environments, the gotchas.
6. **The estate** — repository, domain, hosting, broker, mail, data, trademarks, contracts.
7. **The economics** — revenue, costs, pricing, who pays, what is owed.
8. **The obligations** — support expectations, regulations, customer data, licences, promises made.
9. **The state of play** — open issues, known bugs, planned work, technical debt worth naming.
10. **The first 30 days** — what a new maintainer should do, in order.
11. **What the owner wants** — gift, sale, revenue share, co-maintenance, credit, nothing.
12. **What is not for transfer** — personal accounts, customer data, side projects, the name.
13. **How to verify this brief** — links, commit hashes, dates, a named contact.

A fourteenth section is optional and owner-controlled: **why this is available** — lack of interest, illness, age, death of the maintainer, no collaborator, or prefer not to say. It helps an adopter judge urgency without forcing disclosure. The site should make it easy to say less.

The listing page shows a synopsis (150–250 words), the structured facts, and the brief. Both descriptions may be AI-drafted, but the owner must confirm them, and the page should say so: *"Synopsis drafted with AI, checked by the owner on 16 Nov 2026."* That label is more trustworthy than an unmarked claim of human authorship.

### Handover readiness levels

A simple ladder gives owners a path and adopters a filter:

| Level | Name | What is true |
|---|---|---|
| **L0** | Listed | A description and a contact route exist. |
| **L1** | Documented | Architecture, deploy recipe and access inventory are written down. |
| **L2** | Agent-ready | `continuity.json`, a tested build, a staging environment and an Adoption Brief exist. |
| **L3** | Handover-tested | A second person or agent has run the project from the brief and a dry run of the transfer has happened. |
| **L4** | Transferred | The handover is complete and recorded. |

The badge is not a guarantee of quality; it is a statement about **documentation and rehearsal**, which is exactly what the site can verify.

## 6. The listing: content model

A listing has one controlled category, a free keyword list, and a set of structured facets. Categories (initial set, extendable):

> Web apps & SaaS · Developer tools & libraries · Home automation & IoT · Data & AI · Infrastructure & operations · Desktop & mobile · Games & interactive · Education · Community & non-profit · Content & publishing · Business & productivity · Hardware & embedded · Science & research · Legacy & migration

Structured fields:

| Field | Why it matters |
|---|---|
| **Title** and **one-line pitch** | The only thing most visitors read. |
| **Synopsis** (150–250 words) | Scanning; used in search results and cards. |
| **Detail** / Adoption Brief | Depth; the agent-readable artefact. |
| **Status** | Seeking successor · co-maintainer · buyer · temporary steward · dormant · adopted. |
| **Arrangement** | Gift · sale · revenue share · sponsorship · co-maintenance · other. |
| **Revenue state** | None · potential · proven · costs only. Optional figures and model. |
| **Running costs** | Monthly amount and currency; what it pays for. |
| **Effort** | Hours per month, and what consumes them. |
| **Skills** | Languages, frameworks, domains, AI-agent experience; free and controlled tags. |
| **Users** | None yet · individuals · businesses · public sector · mixed. |
| **Assets** | Repo · domain · hosting · data · broker · mail · trademark · contracts · documentation. |
| **Obligations** | Support, regulatory, data protection, licence constraints, promises to customers. |
| **Licence** | Detected from the repo, confirmed by the owner. |
| **Handover readiness** | L0–L4 (see above). |
| **Jurisdiction / language / timezone** | For contracts and support realism. |
| **Links** | Repository, live site, docs, demo, screenshots. |
| **Contact mode** | Via the site only (default), or a public contact route. |
| **Freshness** | Last confirmed by owner; expiry date. |

### `continuity.json`

The site should publish a small, versioned manifest that an owner commits to the root of their repository. The site fetches and validates it; the owner's repository remains the source of truth. This is the `security.txt` pattern applied to succession.

```json
{
  "schema": "https://relay.eclecity.net/schema/continuity.json",
  "version": "0.1",
  "project": {
    "name": "Room By Room",
    "repo": "https://github.com/easycoder/rbr",
    "site": "https://rbrheating.com",
    "licence": "MIT"
  },
  "status": "seeking-co-maintainer",
  "summary": "Heating control for homes, run on a local controller.",
  "categories": ["home-automation-iot"],
  "keywords": ["heating", "mqtt", "esp32", "php", "allspeak"],
  "stack": ["python", "php", "allspeak", "mqtt"],
  "revenue": { "state": "potential", "model": "per-install licence" },
  "effort": { "hours_per_month": 4 },
  "handover": {
    "method": "sealed passphrase + 14-day waiting period",
    "tested": true,
    "assets": ["repo", "domain", "hosting", "broker", "mail"],
    "brief": "CONTINUITY.md",
    "contact": "via-site"
  },
  "updated": "2026-11-16"
}
```

Rules: the manifest contains **no secrets and no personal data**; it is validated against the published schema; a listing created on the site can also exist without one (L0–L1), but the higher readiness levels require it.

## 7. Finding: indexing and search

Search is the product. A visitor who cannot narrow 200 projects to five relevant ones will leave.

**At the listing level**

- Full-text search across title, keywords, synopsis and brief, with field weighting.
- Typo tolerance and synonyms (`HVAC` ↔ `heating`; `JS` ↔ `JavaScript`; `ML` ↔ `machine learning`).
- Faceted filtering on status, category, arrangement, revenue state, effort, skills, licence, users, assets, readiness and jurisdiction.
- Sorting by newest, most complete, nearest to ready, lowest effort, revenue potential, and urgency.
- "More like this" on a project page, derived from category, keywords and stack.

**At the site level**

- One canonical page per project; stable URLs that survive edits.
- Category and keyword landing pages only when they contain enough listings to be useful (avoid thin pages).
- `sitemap.xml`, RSS/Atom feeds (all listings, and per category), Open Graph cards, `schema.org` `SoftwareApplication` and `Offer` markup.
- A public read-only JSON API: `/api/projects.json`, `/api/project/<slug>.json`, `/api/search?q=…`.
- `llms.txt` and a short "for agents" page explaining the manifest, the API and how to cite a listing.
- A copyable **agent brief**: the repository URL, the questions to ask, and the output format the adopter wants. The audience is AI-capable; make the site the easiest place to point an agent at.

**Anti-graveyard mechanics**

- Listings carry an expiry date. A reminder asks the owner to confirm; unconfirmed listings become **stale** and are visually marked, then archived.
- "Adopted" is a first-class outcome, with an optional success note from both sides.
- A public statistics page: live listings, introductions made, adoptions recorded, median time to first contact. Honest numbers build more trust than testimonials.
- A zero-result search is a demand signal: "tell us what you were looking for" feeds the next round of outreach.

**Implementation note.** The database is the source of truth once accounts and expressions of interest exist. A build/refresh step exports the public listings to `search-index.json`, `projects.json`, feeds and sitemap; the browser-side AllSpeak UI searches that export with a small library (MiniSearch or Pagefind), and a PHP search endpoint provides a no-JS fallback and the stable public API. Search results themselves are `noindex`; the canonical, crawlable pages are the generated listing, category and keyword pages.

## 8. Connecting: rooms, not a lobby

A single open chat room would be the wrong first move. It is hard to moderate, attracts spam and drive-by advice, and puts people who are discussing a business transfer into a public space. It would also compete with the part that actually works: a structured introduction.

**The flow**

1. **Express interest** — a short form: relevant experience, time available, what you would do in the first 30 days, and what you want from the project. Rate-limited; no contact details revealed.
2. **Owner reviews** — sees the expressions, accepts up to a small number, and a **project room** opens.
3. **The room** — a private, threaded conversation for that project. Participants: the owner and accepted adopters. The owner can close it, remove a participant or hand over the room.
4. **Due diligence** — the room is where the brief is questioned, access is discussed, and a written agreement is reached. The site does not handle money or credentials.
5. **Outcome** — the owner marks the project adopted, dormant or still seeking; the room is archived.

**The room, concretely**

- Persisted in the `messages` table, one row per message, escaped and rendered as Markdown with a safe allow-list.
- Live delivery by Server-Sent Events or a short poll at launch; MQTT can be added later if live typing or scale demands it. The existing chat project's topic/post/reply model is a good starting shape; its password hashing would need upgrading before reuse.
- No file uploads at launch (malware and data-leak risk). Links are allowed, with a warning.
- A fixed banner: *"Do not post passwords, API keys, customer data or passphrases. Use the project's own secure channel for those."*
- Retention: rooms close 90 days after the last message and are deleted 90 days after that, with an export for participants.
- Notifications by email; in-app unread counts later.
- Reporting and blocking from day one; a published moderation policy.

**What about a public chat room?** Add one only when there is moderation capacity and a reason — for example a fortnightly "continuity surgery" where owners can ask questions live. A scheduled event is easier to moderate than a permanent lobby, and it gives the site a heartbeat.

## 9. Trust, safety and the law

This is a service where strangers hand over assets. Trust is the feature.

**Verification**

- **Owner identity and control**: GitHub OAuth with a check of repository rights is the primary route; a DNS TXT record or a `.well-known/continuity.txt` file in the repository is the fallback. A manual review is the last resort.
- **Facts, not judgments**: show repository last-commit date, open issues, licence detected, site reachability and brief completeness as facts. Do not invent a "quality score".
- **Badges** for verified owner, complete dossier, handover tested and agent-ready. Each badge links to what it means.
- **Freshness** as a visible property: "confirmed by the owner 3 days ago" or "stale — confirm or archive".

**Abuse and safety**

- No payments, no escrow, no commission, no holding of funds at launch. This avoids a regulatory burden and removes the most attractive scam.
- Contact details are revealed only by mutual consent; the default is an email relay through the site.
- Warnings about the known failure modes: fake owners, asset stripping, "pay a fee to take over", requests for production credentials before an agreement, and pressure to act fast.
- A clear rule that listings must not contain secrets or personal data, and that the site will remove them.
- **Human review before publication.** Every listing starts as `pending`; the
  public build only includes `approved` listings, so an automated flood of
  repositories cannot reach the catalogue without a person approving each one.
- **Edits are reviewed too.** An owner can revise an approved listing; the
  live page stays up while the revision is pending, and the new content
  replaces it only after a person approves it.
- Rate limits, a honeypot, email verification and GitHub OAuth for submission; CAPTCHA only if needed.
- A single named moderation contact and a published response expectation.

**Legal posture**

- Terms of service that say plainly: the site is an introduction service; it is not a broker, agent, recruiter, escrow, trustee or adviser; it does not guarantee any outcome.
- A privacy notice: what is collected, why, where it is stored, how long, how to delete it. UK GDPR alignment; no special-category data; no sale of data.
- No third-party advertising or tracking at launch. Minimal, privacy-respecting analytics.
- A moderation policy with grounds for removal and an appeal route.
- A note that transfers may need legal or tax advice, and that the site does not give it.
- If a listing is effectively a job, it must say so; the site is not a job board and should not drift into one.

**The AI disclosure**

Every field that may be AI-drafted carries a label and an owner-confirmation date. The owner is accountable for the content. This is both honest and practical: it tells the adopter which parts have been checked by a human.

## 10. The site: pages and structure

```
/                         Home — two doors: "I need a successor" / "I want a project"
/projects                 Search and browse, with facets
/projects/<slug>          Listing: synopsis, facts, brief, interest button, room status
/categories/<slug>        Landing pages (only when populated)
/keywords/<slug>          Landing pages (only when populated)
/submit                   Multi-step submission with live preview and AI-draft prompts
/dashboard                Owner: listings, expressions, rooms, refresh, archive
/adopt                    Adopter profile: skills, interests, availability, alerts
/rooms/<id>               Private project room
/learn                     Guides, templates, case studies, the standard
/learn/<slug>             Individual guides
/standard                 The Adoption Brief and continuity.json specification
/agents                   For AI agents: API, llms.txt, prompt pack
/about                    What this is and is not; who runs it; governance
/safety                   Scams, verification, reporting, what not to share
/legal/terms              Terms of service
/legal/privacy            Privacy notice
/status                   Public statistics and changelog
```

The home page should not try to explain everything. It should present the two journeys, three featured projects with real detail, the promise ("fewer, better listings"), and the standard. The "how it works" section can be one diagram with five steps.

## 11. How it is built

The site follows the eclecity pattern — DreamHost, PHP, static files, rsync deployment — with two choices confirmed in review: **browser-based AllSpeak for the UI** and **a database for the things that are not content**.

**The split**

| Layer | Choice | Why |
|---|---|---|
| Public catalogue | HTML generated from the database by a CLI build script | Crawlable and linkable; search engines and agents do not run AllSpeak or JavaScript. |
| UI runtime | Browser-based AllSpeak (`allspeak.js`) | Consistent with doclets, chat and the rest of the estate; the UI reads like the other projects. |
| Interactive surfaces | AllSpeak app scripts: search and filters, submission wizard, dashboard, rooms | The parts that need state and events; progressive enhancement over the generated pages. |
| Backend | PHP 8 with PDO and prepared statements | Fits DreamHost, no framework, easy to audit and self-host. |
| Database | MySQL 8 / MariaDB, `utf8mb4`, InnoDB | Accounts, listings, interests, rooms, messages, reports. Provisioned on the same account as the site. |
| Search index | Generated JSON from the database, searched with MiniSearch in the browser; `search.php` fallback | Fast, cacheable, no search server to run. |
| Auth | Magic link by email; GitHub OAuth for owner verification | No passwords to store or leak. |
| Rooms | Messages in the database; SSE or short polling at launch; MQTT later if needed | Durable and simple; works on shared hosting. |
| Email | `mail()` or authenticated SMTP, with SPF and DKIM | Magic links, notifications and expiry reminders. |
| Deploy | `deploy.sh` with rsync, modelled on RBR's | Familiar, reviewable, rollback-friendly. |
| Analytics | Server logs plus a small first-party counter | No third-party tracking, no cookie banner. |

**How AllSpeak and pre-rendered HTML coexist**

Every public page is rendered as HTML by the build step, using the same CSS and layout as the AllSpeak app. The page is complete without JavaScript: a listing, a category page, a guide. AllSpeak then takes over the interactive layer — it fetches the search export or `/api/projects.json`, renders the filter UI, and drives the submission wizard, dashboard and rooms. A `<noscript>` fallback keeps the catalogue usable, and forms post directly to PHP if the script fails.

This is a deliberate compromise, not a rejection of AllSpeak. A pure AllSpeak single-page app would hide the catalogue from search engines and from agents that fetch HTML, and would make every listing depend on a CDN runtime. The site's public face is therefore pre-rendered; its working parts are AllSpeak. If AllSpeak later gains server-side rendering, the build step can be replaced without changing a single URL.

**Why a database**

Once owners can edit listings, adopters can express interest, and rooms hold messages, files with `flock` become a liability: no joins for search, no transaction for "accept this one and decline the others", no clean audit trail. MySQL is already available on the DreamHost account. The schema is deliberately small:

- `users` — id, email, display name, GitHub id, verified-at, created-at.
- `projects` — id, slug, owner-id, title, pitch, synopsis, brief (Markdown), status, review status, arrangement, category, revenue state and model, monthly cost and currency, effort hours, users, licence, jurisdiction, language, timezone, repository and site URLs, readiness level, contact mode, created/updated/confirmed/expires timestamps.
- `project_keywords`, `project_skills`, `project_assets`, `project_obligations` — one row per value, indexed for facets.
- `interests` — project-id, user-id, message, status (new/accepted/declined), created-at.
- `rooms`, `room_members`, `messages` — the Phase 2 conversation layer.
- `magic_links`, `sessions` — authentication.
- `reports`, `audit_log` — moderation and the record of who changed what.

Indices on `status`, `category`, `readiness` and `expires_at`, plus the keyword and skill join tables. A full-text index (or a generated search table) supports the server-side fallback. The schema ships as `db/schema.sql` with numbered migrations; seed data for local development sits in `db/seed.sql`.

**Security baseline**

- All output escaped; Markdown rendered with a safe allow-list.
- CSRF tokens on every state-changing request; SameSite cookies; HttpOnly sessions; prepared statements everywhere.
- Rate limits on submission, interest, messaging, login and magic-link routes.
- No secrets in the repository; configuration above the web root, as with RBR's host settings.
- Signed, expiring magic links; argon2id only if passwords are ever introduced.
- Database backups, a scheduled content export to JSON, a staging subdomain, and a deploy that can be rolled back.
- A `security.txt` and a contact route for reports.

**Open source and self-hosting**

Apache-2.0, the repository's existing licence. The `README` should offer a five-minute local setup: `.env.example`, `db/schema.sql`, `db/seed.sql`, `deploy.sh.example`, and no proprietary service in the critical path. Contributions arrive by pull request, with `CONTRIBUTING.md` and `CODE_OF_CONDUCT.md` in place before launch. The `continuity.json` schema and the Adoption Brief template live in the repository and are versioned independently of the site, so another directory — or a project with no directory at all — can reuse them.

**The site's own continuity.** It would be embarrassing for this site to be the one project with no successor. Publish its own `continuity.json`, a continuity plan, a named backup maintainer and a sealed-passphrase arrangement for the hosting and domain. Dogfooding is both the right thing and the best marketing.

## 12. Seeding and content

The hardest problem is the cold start, and it is solved with curated supply, not marketing.

- **Seed with your own work.** Relay itself first, then RBR (seeking a co-maintainer, handover mechanism implemented, honest about the missing successor), doclets, mqttchat, the stream/account app, the AllSpeak documentation tooling. These are real, documented and varied — exactly the examples the standard needs.
- **Write four cornerstone guides**: *What continuity means for a one-person project*; *Packaging a project for an AI-capable successor*; *The sealed-passphrase pattern* (the RBR case study); *Due diligence for adopters*.
- **Publish the standard**: `continuity.json` v0.1, the Adoption Brief template, and a validator that owners can run in their own repository.
- **Invite the first ten personally.** A personal email to a maintainer you respect is worth more than a thousand visitors. Ask them to list one project, not to "join a platform".
- **Show the work.** A public changelog and statistics page, including the listings that did not find an adopter. Honesty about the hard cases is the differentiator.

## 13. Free, open source and sustainable

- **Free at launch and after**: listings, search, rooms and the standard. No commission, no escrow, no paid placement, no paid review tier.
- **Open source**: Apache-2.0, self-hostable, with the standard versioned independently of the site. The value is the network and the standard, not a locked platform.
- **Costs**: hosting and mail are already in place; the cash cost should stay under a few tens of pounds a month. Curation and moderation are the real cost, which is why the launch scope is deliberately small.
- **Funding if it is ever needed**: donations or sponsorship that buy no ranking, with the accounts published. Start with no funding at all.
- **Volunteers**: recruit the first moderators and reviewers from the maintainers who list projects. A community checklist replaces a paid review tier: the readiness badge is earned by evidence and a second pair of eyes, not bought.
- **Dogfooding**: the site's own continuity plan, named backup maintainer, and a published succession route for the domain and repository.

## 14. Delivery plan

**Phase 0 — Decide and seed (week 1)**

Lock the name Relay, write the one-page manifesto, create the database schema and a local seed, generate the first HTML pages from 5–8 seed listings, add `continuity.json` v0.1 and an Adoption Brief template, and outline the four guides. *Done when* a stranger can understand the proposition and one owner asks to be listed.

**Phase 1 — MVP (weeks 2–4)**

Listing pages generated from the database, AllSpeak search and facets, owner submission with magic-link editing, moderation queue, expression-of-interest relay by email, expiry reminders, public JSON API, sitemap and feeds, safety and legal pages. *Done when* 20 quality listings are live and the first three expressions of interest have been delivered.

**Phase 2 — Rooms and accounts (weeks 5–8)**

Per-project rooms stored in the database, notifications, adopter profiles, saved searches and alerts, GitHub OAuth verification, automated freshness checks, public statistics. *Done when* the first room has produced a serious conversation and a listing has moved to "adoption in progress".

**Phase 3 — Scale and sustain (weeks 9–12)**

MQTT live rooms if needed, an MCP server or agent toolkit, translations, partnerships, case studies, and the community review process. *Done when* the first completed adoption is recorded and the site is running within its means.

Each phase should be shippable on its own. If Phase 0 attracts no owners, that is a finding worth having before building Phase 2.

## 15. Risks and mitigations

| Risk | Mitigation |
|---|---|
| **Empty marketplace** — more projects than adopters, so listings rot | Curate supply; seed with strong examples; publish honest statistics; target adopters directly through communities where AI-capable builders already are. |
| **Graveyard effect** — a page of abandoned projects repels visitors | Readiness levels, expiry, archive, "adopted" outcomes, and a launch promise of quality over quantity. |
| **Scams and asset stripping** — a fake owner or a predatory adopter | Verification, no payments, no credentials, warnings, reporting, moderation, a written agreement before any transfer. |
| **Legal exposure** — a failed handover blamed on the site | Clear terms, no guarantee, no brokerage, no advice; a documented process and an audit trail. |
| **Moderation burnout** | Async-first; rooms only after accepted interest; rate limits; a published policy; volunteer moderators when volume justifies it. |
| **Privacy breach** | Data minimisation; no secrets; short retention; access control; backups encrypted at rest. |
| **AI-generated nonsense** in briefs | Owner confirmation, labels, factual fields verified from the repository, and a community review checklist that checks the brief. |
| **The site becomes a single point of failure** | Static-first, open content, its own continuity plan, mirrors, and a named backup maintainer. |
| **Scope creep** | Phase gates; each phase has a single success condition; no payments, no job board, no general social network. |

## 16. Decisions recorded

1. **Name.** Relay. The host is `relay.eclecity.net`; a separate brand domain can follow later if it proves useful.
2. **Launch scope.** Listings, search and an expression-of-interest relay first; private project rooms in Phase 2. No public lobby at launch.
3. **Build.** Pre-rendered HTML for the public catalogue, browser-based AllSpeak for the interactive surfaces, PHP for the backend, MySQL for the data. The public pages stay crawlable; the app is AllSpeak.
4. **Money and licence.** Free at launch and after, with no paid tier; Apache-2.0 and self-hostable.
5. **First listing.** Relay itself, with its current honest status: the database and deployment are in place, the catalogue is being built, and a co-maintainer is sought. RBR follows, with its handover mechanism built and tested and no named successor yet.

The remaining choices — column types, the AllSpeak version pin, the exact deploy target, the first five categories — are ordinary engineering decisions and do not need another round of review.

## 17. Recommendation

Build the smallest thing that can produce a real handover: a database-backed catalogue with excellent search, a submission flow that forces an honest brief, an expression-of-interest relay, and eight seed listings — Relay first, then RBR. Add rooms once there are conversations worth having. Keep the standard (`continuity.json` and the Adoption Brief) independent of the site so that it can outlive the site, which is the whole point.

The site's promise should be modest and specific: **"We help a project find its next maintainer, and we help that maintainer decide before they commit."** If it does that ten times, it has earned the right to grow.
