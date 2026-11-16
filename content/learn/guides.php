<?php

/**
 * Guide metadata for the public /learn/ pages.
 *
 * The build renders each Markdown file with the same layout as the catalogue.
 */

declare(strict_types=1);

return [
    'handover' => [
        'title' => 'When the maintainer disappears',
        'description' => 'A sealed passphrase, a waiting period and a prepared site: how a handover mechanism works, using RBR as the worked example.',
        'markdown' => __DIR__ . '/handover.md',
    ],
];
