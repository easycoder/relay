<?php

/** @var array<string, mixed> $project */

$backUrl = $backUrl ?? '/projects/';
$backText = $backText ?? 'All projects';
$stage = (string) ($project['stage'] ?? 'live');
$status = status_label($project['status']);

$facts = [];
$facts['Status'] = $status;
$facts['Stage'] = stage_label($stage);
$likeLabel = like_count_label((int) ($project['like_count'] ?? 0));

if ($likeLabel !== '') {
    $facts['Likes'] = $likeLabel;
}

if (!empty($project['owner_name'])) {
    $facts['Owner'] = $project['owner_name'];
}

if (!empty($project['arrangement'])) {
    $facts['Arrangement'] = arrangement_label($project['arrangement']);
}

if (!empty($project['revenue_state'])) {
    $facts['Revenue'] = revenue_label($project['revenue_state']);
}

if ($project['monthly_cost'] !== null) {
    $facts['Running costs'] = format_money($project['monthly_cost'], $project['currency']) . ' / month';
} elseif (!empty($project['cost_description'])) {
    $facts['Running costs'] = $project['cost_description'];
}

if ($project['effort_hours_per_month'] !== null) {
    $effort = $project['effort_hours_per_month'] . ' hours / month';
    if (!empty($project['effort_description'])) {
        $effort .= ' — ' . $project['effort_description'];
    }
    $facts['Effort'] = $effort;
} elseif (!empty($project['effort_description'])) {
    $facts['Effort'] = $project['effort_description'];
}

if (!empty($project['user_scope'])) {
    $facts['Users'] = user_scope_label($project['user_scope']);
}

if (!empty($project['licence'])) {
    $facts['Licence'] = $project['licence'];
}

if ($stage !== 'idea') {
    $facts['Readiness'] = readiness_label((int) $project['readiness']);
}

if (!empty($project['published_at'])) {
    $facts['Listed'] = format_date($project['published_at']);
}

if (!empty($project['expires_at'])) {
    $facts['Expires'] = format_date($project['expires_at']);
}

if (!empty($project['jurisdiction'])) {
    $facts['Jurisdiction'] = $project['jurisdiction'];
}

if (!empty($project['language'])) {
    $facts['Language'] = $project['language'];
}

if (!empty($project['timezone'])) {
    $facts['Timezone'] = $project['timezone'];
}

if (!empty($project['keywords'])) {
    $facts['Keywords'] = implode(', ', $project['keywords']);
}

if (!empty($project['skills'])) {
    $facts['Skills'] = implode(', ', $project['skills']);
}

if (!empty($project['assets'])) {
    $facts['Assets'] = implode(', ', array_map('asset_label', $project['assets']));
}

?>
<article class="listing">
    <p class="meta"><a href="<?= e($backUrl) ?>">← <?= e($backText) ?></a></p>

    <header class="listing-header">
        <p class="eyebrow"><?= e($project['category_name']) ?></p>
        <h1><?= e($project['title']) ?></h1>
        <p class="lede"><?= e($project['pitch']) ?></p>
        <p class="badges">
            <span class="badge"><?= e(stage_label($stage)) ?></span>
            <span class="badge badge-quiet"><?= e($status) ?></span>
            <?php if (($project['review_status'] ?? 'approved') === 'approved'): ?>
                <span class="badge badge-quiet">Approved</span>
            <?php endif; ?>
            <?php if ($stage !== 'idea'): ?>
                <span class="badge badge-quiet"><?= e(readiness_label((int) $project['readiness'])) ?></span>
            <?php endif; ?>
            <?php if (edit_marker_visible($project)): ?>
                <span class="badge badge-quiet">Edited <?= e(format_date($project['edited_at'])) ?></span>
            <?php endif; ?>
            <?php if (!empty($project['is_draft'])): ?>
                <span class="badge badge-quiet">Draft</span>
            <?php endif; ?>
        </p>
    </header>

    <?php if (!empty($project['is_draft'])): ?>
        <p class="notice">This listing is a draft. The synopsis and brief were drafted with AI and have not yet been confirmed by the owner.</p>
    <?php elseif (!empty($project['synopsis_ai_drafted']) || !empty($project['brief_ai_drafted'])): ?>
        <p class="meta">Synopsis and brief drafted with AI, checked by the owner on <?= e(format_date($project['confirmed_at'])) ?>.</p>
    <?php endif; ?>

    <div class="listing-body">
        <div class="listing-main">
            <section>
                <h2>Synopsis</h2>
                <p><?= nl2br(e($project['synopsis'])) ?></p>
            </section>

            <?php if (!empty($project['brief'])): ?>
                <section class="brief">
                    <h2><?= e(brief_label($stage)) ?></h2>
                    <?= render_markdown($project['brief'], 1) ?>
                </section>
            <?php endif; ?>

            <?php if (!empty($project['obligations'])): ?>
                <section>
                    <h2>Obligations</h2>
                    <ul>
                        <?php foreach ($project['obligations'] as $obligation): ?>
                            <li><?= e($obligation) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>

        <aside class="listing-aside">
            <h2>Facts</h2>
            <dl class="facts">
                <?php foreach ($facts as $label => $value): ?>
                    <dt><?= e($label) ?></dt>
                    <dd><?= e((string) $value) ?></dd>
                <?php endforeach; ?>
            </dl>

            <?php if (!empty($project['repo_url']) || !empty($project['site_url']) || !empty($project['docs_url']) || !empty($project['demo_url'])): ?>
                <h2>Links</h2>
                <ul class="links">
                    <?php if (!empty($project['repo_url'])): ?>
                        <li><a href="<?= e($project['repo_url']) ?>" rel="noopener">Repository</a></li>
                    <?php endif; ?>
                    <?php if (!empty($project['site_url'])): ?>
                        <li><a href="<?= e($project['site_url']) ?>" rel="noopener">Live site</a></li>
                    <?php endif; ?>
                    <?php if (!empty($project['docs_url'])): ?>
                        <li><a href="<?= e($project['docs_url']) ?>" rel="noopener">Documentation</a></li>
                    <?php endif; ?>
                    <?php if (!empty($project['demo_url'])): ?>
                        <li><a href="<?= e($project['demo_url']) ?>" rel="noopener">Demo</a></li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>

            <div class="interest">
                <h2>Express interest</h2>
                <?php if (!empty($project['show_interest'])): ?>
                    <p>Tell the owner what you would do and what you need. Your email is shared only if they accept.</p>
                    <p><a class="button" href="/interest/?project=<?= e($project['slug']) ?>">Express interest</a></p>
                <?php else: ?>
                    <p>Expressions of interest open when this listing is published.</p>
                <?php endif; ?>
                <p class="meta">Relay never asks for a passphrase. <a href="/learn/handover/">How a handover mechanism works</a>.</p>
            </div>

            <?php if ($project['confirmed_at']): ?>
                <p class="meta">Last confirmed by the owner on <?= e(format_date($project['confirmed_at'])) ?>.</p>
            <?php endif; ?>
        </aside>
    </div>
</article>
