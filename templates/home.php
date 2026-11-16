<?php

/** @var array<int, array<string, mixed>> $projects */
/** @var string $siteUrl */

?>
<section class="hero">
    <p class="eyebrow">A registry for continuity</p>
    <h1>Find the next maintainer for a project that matters.</h1>
    <p class="lede">Relay helps a project owner describe what they have and what a successor would inherit, so the right person — or their agent — can decide before they commit.</p>
    <p class="actions">
        <a class="button" href="/projects/">Browse projects</a>
        <a class="button button-secondary" href="/submit/">Submit a project</a>
        <a class="button button-secondary" href="#how">How it works</a>
    </p>
</section>

<section class="section" id="how">
    <h2>How it works</h2>
    <ol class="steps">
        <li><strong>Describe what you have.</strong> A structured listing and an Adoption Brief that forces the honest questions: access, authority, context, economics, obligations and what is not for transfer.</li>
        <li><strong>Publish it.</strong> The catalogue is rendered as HTML, so search engines and agents can read it. No walled garden, no login to browse.</li>
        <li><strong>Search and compare.</strong> Filter by category, status, readiness, effort, skills, licence and more.</li>
        <li><strong>Express interest.</strong> A short, rate-limited form. No contact details are revealed until the owner accepts.</li>
        <li><strong>Talk, verify, hand over.</strong> A private room for due diligence, then the project's own handover process. Relay is not a party to the transfer.</li>
    </ol>
</section>

<section class="section">
    <div class="section-heading">
        <h2>Featured projects</h2>
        <a href="/projects/">All projects</a>
    </div>

    <?php if ($projects === []): ?>
        <p>No listings yet. The first one will appear here.</p>
    <?php else: ?>
        <div class="cards">
            <?php foreach ($projects as $project): ?>
                <?php require __DIR__ . '/partials/card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section note">
    <h2>Fewer, better listings</h2>
    <p>A directory of abandoned repositories is worse than useless. Relay optimises for complete, honest listings — the ones a capable stranger can actually assess. The first listing is Relay itself.</p>
    <p>Relay finds the next maintainer; it does not replace the project's own handover mechanism. If you are planning for the day the maintainer cannot continue, read <a href="/learn/handover/">how a handover mechanism works</a>.</p>
</section>
