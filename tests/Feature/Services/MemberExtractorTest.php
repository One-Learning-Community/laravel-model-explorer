<?php

use OneLearningCommunity\LaravelModelExplorer\Data\MemberData;
use OneLearningCommunity\LaravelModelExplorer\Services\MemberExtractor;
use Workbench\App\Models\MemberShowcase;

/** @return Illuminate\Support\Collection<string, MemberData> */
function showcaseMembers(): Illuminate\Support\Collection
{
    return collect(MemberExtractor::forModel(MemberShowcase::class, ['owner']))->keyBy('name');
}

it('lists first-party members and excludes inherited framework methods', function () {
    $names = showcaseMembers()->keys();

    expect($names)->toContain('archive', 'booted', 'shortLabel', 'computeChecksum', 'owner', 'MAX_TAGS', 'fillable', 'table')
        ->and($names)->not->toContain('save')
        ->and($names)->not->toContain('delete')
        ->and($names)->not->toContain('newQuery');
});

it('classifies member kinds heuristically', function () {
    $members = showcaseMembers();

    expect($members['archive']->kind)->toBe('business')
        ->and($members['booted']->kind)->toBe('lifecycle')
        ->and($members['shortLabel']->kind)->toBe('accessor')
        ->and($members['computeChecksum']->kind)->toBe('method')
        ->and($members['owner']->kind)->toBe('relation')
        ->and($members['MAX_TAGS']->kind)->toBe('constant')
        ->and($members['fillable']->kind)->toBe('config');
});

it('captures visibility, static, signatures and constant values', function () {
    $members = showcaseMembers();

    expect($members['archive']->signature)->toBe('archive(DateTimeInterface $at): void')
        ->and($members['archive']->visibility)->toBe('public')
        ->and($members['booted']->visibility)->toBe('protected')
        ->and($members['booted']->static)->toBeTrue()
        ->and($members['computeChecksum']->visibility)->toBe('private')
        ->and($members['MAX_TAGS']->value)->toBe('5');
});

it('attributes a trait-provided member to the trait file, not the model', function () {
    $owner = showcaseMembers()->get('owner');

    expect($owner->snippet['file'])->toContain('Concerns'.DIRECTORY_SEPARATOR.'HasOwner.php');
});

it('returns an empty list for a non-existent class without throwing', function () {
    expect(MemberExtractor::forModel('Workbench\\App\\Models\\Nope'))->toBe([]);
});
