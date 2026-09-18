<?php

use Shazzoo\ContentStudio\Support\ArticleImagePlaceholders;

const DIAGRAM_ID = '355f5250-3e28-435d-9a53-1d934c21a52f';
const ILLUSTRATION_ID = '0bcdaaae-23ee-4484-b05b-2251e2d2a117';

/**
 * Een placeholder zoals de Engine hem aanlevert: het hele commentaar staat in
 * `placeholder` en de url in `image_url`.
 */
function enginePlaceholder(string $id, string $type, ?string $caption = null): array
{
    return [
        'placeholder' => "<!--{$type}-placeholder:{$id}-->",
        'image_url' => "https://engine.test/{$type}.png",
        'alt' => 'Alt tekst',
        'caption' => $caption,
    ];
}

function bodyWith(string ...$ids): string
{
    $html = '<p>intro</p>';

    foreach ($ids as $id) {
        $type = $id === ILLUSTRATION_ID ? 'illustration' : 'diagram';
        $html .= "<!--{$type}-placeholder:{$id}-->";
    }

    return $html.'<p>slot</p>';
}

it('vervangt zowel diagrammen als illustraties uit de samengevoegde lijst', function () {
    $html = ArticleImagePlaceholders::replace(
        bodyWith(DIAGRAM_ID, ILLUSTRATION_ID),
        ['image_placeholders' => [
            ['type' => 'diagram'] + enginePlaceholder(DIAGRAM_ID, 'diagram', 'Onderschrift'),
            ['type' => 'illustration'] + enginePlaceholder(ILLUSTRATION_ID, 'illustration'),
        ]]
    );

    expect($html)
        ->toContain('class="article-image article-diagram"')
        ->toContain('class="article-image article-illustration"')
        ->not->toContain('placeholder:');
});

it('valt terug op de oude lijsten als image_placeholders leeg is', function () {
    // De Engine stuurt het veld al mee voordat hij het vult; dat mag de oude
    // lijsten niet wegdrukken.
    $html = ArticleImagePlaceholders::replace(
        bodyWith(DIAGRAM_ID, ILLUSTRATION_ID),
        [
            'image_placeholders' => [],
            'diagram_placeholders' => [enginePlaceholder(DIAGRAM_ID, 'diagram', 'Onderschrift')],
            'illustration_placeholders' => [enginePlaceholder(ILLUSTRATION_ID, 'illustration')],
        ]
    );

    expect($html)
        ->toContain('class="article-image article-diagram"')
        ->toContain('class="article-image article-illustration"')
        ->not->toContain('placeholder:');
});

it('gebruikt de samengevoegde lijst en haalt een afbeelding niet twee keer op', function () {
    $resolved = [];

    $html = ArticleImagePlaceholders::replace(
        bodyWith(DIAGRAM_ID),
        [
            'image_placeholders' => [['type' => 'diagram'] + enginePlaceholder(DIAGRAM_ID, 'diagram', 'Nieuw')],
            'diagram_placeholders' => [enginePlaceholder(DIAGRAM_ID, 'diagram', 'Oud')],
        ],
        function (string $url, ?string $id, array $entry) use (&$resolved): string {
            $resolved[] = $id;

            return '/storage/'.$entry['type'].'s/'.$id.'.png';
        }
    );

    expect($resolved)->toHaveCount(1)
        ->and($html)->toContain('Nieuw')->not->toContain('Oud')
        ->and($html)->toContain('/storage/diagrams/');
});

it('rendert een onbekend toekomstig type zonder aanpassing', function () {
    $id = '11111111-1111-1111-1111-111111111111';

    $html = ArticleImagePlaceholders::replace(
        "<!--chart-placeholder:{$id}-->",
        ['image_placeholders' => [[
            'placeholder' => "<!--chart-placeholder:{$id}-->",
            'image_url' => 'https://engine.test/chart.svg',
            'alt' => 'Omzet',
            'type' => 'chart',
        ]]]
    );

    expect($html)->toContain('class="article-image article-chart"')->not->toContain('placeholder:');
});

it('geeft het type door aan de resolver zodat elk type zijn eigen map krijgt', function () {
    $types = [];

    ArticleImagePlaceholders::replace(
        bodyWith(DIAGRAM_ID, ILLUSTRATION_ID),
        ['image_placeholders' => [
            ['type' => 'diagram'] + enginePlaceholder(DIAGRAM_ID, 'diagram'),
            ['type' => 'illustration'] + enginePlaceholder(ILLUSTRATION_ID, 'illustration'),
        ]],
        function (string $url, ?string $id, array $entry) use (&$types): string {
            $types[$id] = $entry['type'];

            return $url;
        }
    );

    expect($types)->toBe([DIAGRAM_ID => 'diagram', ILLUSTRATION_ID => 'illustration']);
});

it('laat een placeholder staan waar geen afbeelding bij hoort', function () {
    $html = ArticleImagePlaceholders::replace(bodyWith(DIAGRAM_ID), ['image_placeholders' => []]);

    expect($html)->toContain('diagram-placeholder:'.DIAGRAM_ID);
});

it('leest ook placeholders die onder meta staan', function () {
    $html = ArticleImagePlaceholders::replace(
        bodyWith(DIAGRAM_ID),
        ['meta' => ['diagram_placeholders' => [enginePlaceholder(DIAGRAM_ID, 'diagram', 'Onderschrift')]]]
    );

    expect($html)->toContain('class="article-image article-diagram"');
});
