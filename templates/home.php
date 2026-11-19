<?php

/** @var array<int, array<string, mixed>> $projects */
/** @var string $siteUrl */

?>
<section class="hero">
    <p class="eyebrow">A registry for continuity</p>
    <h1>Find what happens next for a project, product or idea.</h1>
    <p class="lede">Relay helps an owner describe what they have — or what they want to exist — and what someone taking it on would inherit, so the right person, or their agent, can decide before they commit.</p>
    <p class="actions">
        <a class="button" href="/find/">Browse listings</a>
        <a class="button button-secondary" href="/submit/">List a project or idea</a>
        <a class="button button-secondary" href="#how">How it works</a>
    </p>
</section>

<section class="section" id="how">
    <h2>How it works</h2>
    <ol class="steps">
        <li><strong>Describe what you have, or what you want to exist.</strong> A structured listing and a brief that forces the honest questions. A live project inherits access, authority, economics and obligations; an idea needs the problem, prior art and a cheap test.</li>
        <li><strong>Enter the waiting list.</strong> A reviewer can approve it, or a threshold of reasoned likes can. A rejection blocks automatic approval, and a reviewer can veto a community decision.</li>
        <li><strong>Search and compare.</strong> Filter by stage, category, status, readiness, effort, skills, licence and more.</li>
        <li><strong>Express interest.</strong> A short, rate-limited form. No contact details are revealed until the owner accepts.</li>
        <li><strong>Talk, verify, hand over — or test it.</strong> Due diligence, then the project's own handover process. Relay is not a party to the transfer.</li>
    </ol>
</section>

<section class="section">
    <div class="section-heading">
        <h2>Recent listings</h2>
        <a href="/projects/">All listings</a>
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
    <p>A directory of abandoned repositories or half-formed ideas is worse than useless. Relay optimises for complete, honest listings — the ones a capable stranger can actually assess. Listings expire after six months unless the owner confirms they are still needed.</p>
    <p>Relay finds the next maintainer, collaborator or tester; it does not replace the project's own handover mechanism. If you are planning for the day the maintainer cannot continue, read <a href="/learn/handover/">how a handover mechanism works</a>.</p>
</section>
