<?php

/** @var array<string, mixed> $filters */
/** @var array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int} $results */
/** @var array<string, array<string, int>> $facets */
/** @var array<int, array<string, string>> $categories */
/** @var array<string, string> $statuses */
/** @var array<string, string> $arrangements */
/** @var array<int, string> $readiness */
/** @var array<string, string> $efforts */

$rows = $results['rows'] ?? [];
$total = (int) ($results['total'] ?? 0);
$page = (int) ($results['page'] ?? 1);
$pages = (int) ($results['pages'] ?? 1);

$active = array_filter(
    $filters,
    static fn ($value, $key) => $key !== 'page' && $value !== '',
    ARRAY_FILTER_USE_BOTH
);

$pageUrl = static function (int $number) use ($active): string {
    $active['page'] = $number;

    return '/find/?' . http_build_query($active);
};

$count = static fn (string $facet, string $value): int => (int) ($facets[$facet][$value] ?? 0);

?>
<section class="hero auth-hero">
    <p class="eyebrow">Find</p>
    <h1>Find a project</h1>
    <p class="lede">Every listing here is approved and current. Filter by what you can take on, then read the brief.</p>
</section>

<form class="form find-form" method="get" action="/find/">
    <label class="field">
        <span>Words in the listing</span>
        <input type="search" name="q" value="<?= e((string) $filters['q']) ?>" maxlength="100" placeholder="heating, mqtt, invoicing, PHP…">
        <small>Searches the title, pitch, synopsis and brief.</small>
    </label>

    <div class="facet-row">
        <label class="field">
            <span>Category</span>
            <select name="category">
                <option value="">Any</option>
                <?php foreach ($categories as $category): ?>
                    <?php $n = $count('category', (string) $category['slug']); ?>
                    <option value="<?= e((string) $category['slug']) ?>"<?= $filters['category'] === $category['slug'] ? ' selected' : '' ?>><?= e((string) $category['name']) ?> (<?= $n ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Looking for</span>
            <select name="status">
                <option value="">Any</option>
                <?php foreach ($statuses as $value => $label): ?>
                    <?php $n = $count('status', (string) $value); ?>
                    <option value="<?= e((string) $value) ?>"<?= $filters['status'] === (string) $value ? ' selected' : '' ?>><?= e($label) ?> (<?= $n ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Arrangement</span>
            <select name="arrangement">
                <option value="">Any</option>
                <?php foreach ($arrangements as $value => $label): ?>
                    <?php $n = $count('arrangement', (string) $value); ?>
                    <option value="<?= e((string) $value) ?>"<?= $filters['arrangement'] === (string) $value ? ' selected' : '' ?>><?= e($label) ?> (<?= $n ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Readiness</span>
            <select name="readiness">
                <option value="">Any</option>
                <?php foreach ($readiness as $value => $label): ?>
                    <?php $n = $count('readiness', (string) $value); ?>
                    <option value="<?= e((string) $value) ?>"<?= $filters['readiness'] === (string) $value ? ' selected' : '' ?>><?= e($label) ?> (<?= $n ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Effort</span>
            <select name="effort">
                <option value="">Any</option>
                <?php foreach ($efforts as $value => $label): ?>
                    <?php $n = $count('effort', (string) $value); ?>
                    <option value="<?= e((string) $value) ?>"<?= $filters['effort'] === (string) $value ? ' selected' : '' ?>><?= e($label) ?> (<?= $n ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <div class="actions">
        <button class="button" type="submit">Show projects</button>
        <a class="button button-secondary" href="/find/">Clear</a>
    </div>

    <?php if ((string) $filters['keyword'] !== ''): ?>
        <input type="hidden" name="keyword" value="<?= e((string) $filters['keyword']) ?>">
        <p class="meta">Keyword: <strong><?= e((string) $filters['keyword']) ?></strong> — <a href="/find/">remove</a></p>
    <?php endif; ?>
</form>

<?php if (($facets['keywords'] ?? []) !== []): ?>
    <section class="section">
        <h2>Keywords</h2>
        <p class="badges">
            <?php foreach ($facets['keywords'] as $keyword => $n): ?>
                <a class="badge<?= (string) $filters['keyword'] === (string) $keyword ? '' : ' badge-quiet' ?>" href="/find/?keyword=<?= e(urlencode((string) $keyword)) ?>"><?= e((string) $keyword) ?> (<?= (int) $n ?>)</a>
            <?php endforeach; ?>
        </p>
    </section>
<?php endif; ?>

<p class="meta section">
    <?php if ($total === 0): ?>
        No approved listing matches those filters.
    <?php else: ?>
        <?= $total ?> listing<?= $total === 1 ? '' : 's' ?> match<?= $total === 1 ? 'es' : '' ?><?= $pages > 1 ? ', page ' . $page . ' of ' . $pages : '' ?>.
    <?php endif; ?>
</p>

<?php if ($rows !== []): ?>
    <div class="cards">
        <?php foreach ($rows as $row): ?>
            <?php $project = $row; ?>
            <?php require __DIR__ . '/partials/card.php'; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($pages > 1): ?>
    <nav class="actions section" aria-label="Pages">
        <?php if ($page > 1): ?>
            <a class="button button-secondary" href="<?= e($pageUrl($page - 1)) ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $pages): ?>
            <a class="button button-secondary" href="<?= e($pageUrl($page + 1)) ?>">Next</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<p class="meta">Machine-readable: the same filters with <code>&amp;format=json</code>, or the whole catalogue at <a href="/projects.json">/projects.json</a>.</p>
