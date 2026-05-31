<?php

declare(strict_types=1);

namespace Phalanx\Dory\Tests\Unit\Stele;

use Phalanx\Dory\Stele\Epistemic;
use Phalanx\Dory\Stele\SectionKind;
use Phalanx\Dory\Stele\SteleParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SteleParserTest extends TestCase
{
    private SteleParser $parser;

    protected function setUp(): void
    {
        $this->parser = new SteleParser();
    }

    // ── Empty / minimal inputs ──────────────────────────────────────

    #[Test]
    public function empty_string_returns_empty_stele(): void
    {
        $stele = $this->parser->parse('');

        self::assertSame('', $stele->preamble);
        self::assertSame([], $stele->sections);
    }

    #[Test]
    public function whitespace_only_returns_empty_stele(): void
    {
        $stele = $this->parser->parse("  \n\n  \n");

        self::assertSame('', $stele->preamble);
        self::assertSame([], $stele->sections);
    }

    #[Test]
    public function preamble_only_returns_no_sections(): void
    {
        $md = <<<'MD'
        > This is context preamble.
        > It spans two lines.
        MD;

        $stele = $this->parser->parse($md);

        self::assertSame("This is context preamble.\nIt spans two lines.", $stele->preamble);
        self::assertSame([], $stele->sections);
    }

    #[Test]
    public function bare_blockquote_line_becomes_empty_preamble_line(): void
    {
        $md = <<<'MD'
        > First line
        >
        > Third line
        MD;

        $stele = $this->parser->parse($md);

        self::assertSame("First line\n\nThird line", $stele->preamble);
    }

    #[Test]
    public function non_blockquote_lines_before_sections_are_ignored_in_preamble(): void
    {
        $md = <<<'MD'
        # Title
        Some plain text
        > Actual preamble
        MD;

        $stele = $this->parser->parse($md);

        self::assertSame('Actual preamble', $stele->preamble);
    }

    // ── Section detection ───────────────────────────────────────────

    #[Test]
    public function parses_all_six_section_kinds(): void
    {
        $md = <<<'MD'
        ## Constraints

        ## Intents

        ## Forms

        ## Lenses

        ## Passages

        ## State
        MD;

        $stele = $this->parser->parse($md);

        self::assertCount(6, $stele->sections);

        $kinds = array_map(static fn($s) => $s->kind, $stele->sections);
        self::assertSame([
            SectionKind::Constraint,
            SectionKind::Intent,
            SectionKind::Form,
            SectionKind::Lens,
            SectionKind::Passage,
            SectionKind::State,
        ], $kinds);
    }

    #[Test]
    public function unknown_section_heading_is_skipped(): void
    {
        $md = <<<'MD'
        ## Unknown Section

        ## Constraints
        MD;

        $stele = $this->parser->parse($md);

        self::assertCount(1, $stele->sections);
        self::assertSame(SectionKind::Constraint, $stele->sections[0]->kind);
    }

    #[Test]
    public function section_without_entries_has_empty_entries(): void
    {
        $md = <<<'MD'
        ## Constraints

        Some free text here.
        MD;

        $stele = $this->parser->parse($md);

        self::assertCount(1, $stele->sections);
        self::assertSame([], $stele->sections[0]->entries);
    }

    // ── Entry heading parsing ───────────────────────────────────────

    #[Test]
    public function parses_entry_with_certain_epistemic(): void
    {
        $md = <<<'MD'
        ## Constraints

        ### No global state @!
        MD;

        $stele = $this->parser->parse($md);

        $entries = $stele->sections[0]->entries;
        self::assertCount(1, $entries);
        self::assertSame('No global state', $entries[0]->name);
        self::assertSame(Epistemic::Certain, $entries[0]->epistemic);
    }

    #[Test]
    public function parses_entry_with_approximate_epistemic(): void
    {
        $md = <<<'MD'
        ## Intents

        ### Async-first design @~
        MD;

        $stele = $this->parser->parse($md);

        $entries = $stele->sections[0]->entries;
        self::assertCount(1, $entries);
        self::assertSame('Async-first design', $entries[0]->name);
        self::assertSame(Epistemic::Approximate, $entries[0]->epistemic);
    }

    #[Test]
    public function parses_entry_with_stale_epistemic(): void
    {
        $md = <<<'MD'
        ## State

        ### Legacy migration plan @_
        MD;

        $stele = $this->parser->parse($md);

        $entries = $stele->sections[0]->entries;
        self::assertCount(1, $entries);
        self::assertSame('Legacy migration plan', $entries[0]->name);
        self::assertSame(Epistemic::Stale, $entries[0]->epistemic);
    }

    #[Test]
    public function entry_heading_without_epistemic_marker_is_skipped(): void
    {
        $md = <<<'MD'
        ## Constraints

        ### Missing marker
        MD;

        $stele = $this->parser->parse($md);

        self::assertSame([], $stele->sections[0]->entries);
    }

    #[Test]
    public function entry_heading_with_invalid_marker_is_skipped(): void
    {
        $md = <<<'MD'
        ## Constraints

        ### Bad marker @?
        MD;

        $stele = $this->parser->parse($md);

        self::assertSame([], $stele->sections[0]->entries);
    }

    // ── Meta block parsing ──────────────────────────────────────────

    #[Test]
    public function parses_when_triggers(): void
    {
        $md = <<<'MD'
        ## Constraints

        ### Injected config @!
        > when: boot, config-change
        MD;

        $stele = $this->parser->parse($md);

        $entry = $stele->sections[0]->entries[0];
        self::assertSame(['boot', 'config-change'], $entry->when);
    }

    #[Test]
    public function parses_single_when_trigger(): void
    {
        $md = <<<'MD'
        ## Constraints

        ### Single trigger @!
        > when: always
        MD;

        $stele = $this->parser->parse($md);

        $entry = $stele->sections[0]->entries[0];
        self::assertSame(['always'], $entry->when);
    }

    #[Test]
    public function parses_aspects_from_dot_notation(): void
    {
        $md = <<<'MD'
        ## Forms

        ### Service container @!
        > .di .container .injection
        MD;

        $stele = $this->parser->parse($md);

        $entry = $stele->sections[0]->entries[0];
        self::assertSame(['di', 'container', 'injection'], $entry->aspects);
    }

    #[Test]
    public function parses_pointers(): void
    {
        $md = <<<'MD'
        ## Constraints

        ### Config pattern @!
        > <- src/Config/AppConfig.php
        > <- .aimind/knowledge.md
        MD;

        $stele = $this->parser->parse($md);

        $entry = $stele->sections[0]->entries[0];
        self::assertCount(2, $entry->pointers);
        self::assertSame('src/Config/AppConfig.php', $entry->pointers[0]->path);
        self::assertSame('.aimind/knowledge.md', $entry->pointers[1]->path);
    }

    #[Test]
    public function parses_refs_with_subs(): void
    {
        $md = <<<'MD'
        ## Lenses

        ### Architecture overview @!
        > ref: docs/architecture.md
        >   /section-one
        >   /section-two
        MD;

        $stele = $this->parser->parse($md);

        $entry = $stele->sections[0]->entries[0];
        self::assertCount(1, $entry->refs);
        self::assertSame('docs/architecture.md', $entry->refs[0]->base);
        self::assertSame(['/section-one', '/section-two'], $entry->refs[0]->subs);
    }

    #[Test]
    public function parses_ref_without_subs(): void
    {
        $md = <<<'MD'
        ## Lenses

        ### Simple ref @!
        > ref: README.md
        MD;

        $stele = $this->parser->parse($md);

        $entry = $stele->sections[0]->entries[0];
        self::assertCount(1, $entry->refs);
        self::assertSame('README.md', $entry->refs[0]->base);
        self::assertSame([], $entry->refs[0]->subs);
    }

    #[Test]
    public function ref_subs_attach_to_most_recent_ref(): void
    {
        $md = <<<'MD'
        ## Lenses

        ### Multi-ref @!
        > ref: first.md
        >   /sub-a
        > ref: second.md
        >   /sub-b
        MD;

        $stele = $this->parser->parse($md);

        $entry = $stele->sections[0]->entries[0];
        self::assertCount(2, $entry->refs);
        self::assertSame(['/sub-a'], $entry->refs[0]->subs);
        self::assertSame(['/sub-b'], $entry->refs[1]->subs);
    }

    // ── Body parsing ────────────────────────────────────────────────

    #[Test]
    public function parses_body_text(): void
    {
        $md = <<<'MD'
        ## Constraints

        ### No getenv @!
        > when: always
        > .config

        All configuration must be injected at boot time.
        Never call getenv() inside application code.
        MD;

        $stele = $this->parser->parse($md);

        $entry = $stele->sections[0]->entries[0];
        self::assertSame(
            "All configuration must be injected at boot time.\nNever call getenv() inside application code.",
            $entry->body,
        );
    }

    #[Test]
    public function entry_without_body_has_empty_body(): void
    {
        $md = <<<'MD'
        ## Constraints

        ### Headings only @!
        > when: always
        MD;

        $stele = $this->parser->parse($md);

        $entry = $stele->sections[0]->entries[0];
        self::assertSame('', $entry->body);
    }

    #[Test]
    public function leading_blank_lines_between_meta_and_body_are_stripped(): void
    {
        $md = <<<'MD'
        ## Constraints

        ### Spaced out @!
        > when: always



        The actual body starts here.
        MD;

        $stele = $this->parser->parse($md);

        $entry = $stele->sections[0]->entries[0];
        self::assertSame('The actual body starts here.', $entry->body);
    }

    // ── Combined / integration ──────────────────────────────────────

    #[Test]
    public function parses_multiple_entries_in_one_section(): void
    {
        $md = <<<'MD'
        ## Constraints

        ### First rule @!
        > when: always

        ### Second rule @~
        > when: review
        MD;

        $stele = $this->parser->parse($md);

        $entries = $stele->sections[0]->entries;
        self::assertCount(2, $entries);
        self::assertSame('First rule', $entries[0]->name);
        self::assertSame('Second rule', $entries[1]->name);
    }

    #[Test]
    public function parses_multiple_sections_with_entries(): void
    {
        $md = <<<'MD'
        ## Constraints

        ### Hard rule @!
        > when: always

        ## Intents

        ### Soft goal @~
        > when: design
        MD;

        $stele = $this->parser->parse($md);

        self::assertCount(2, $stele->sections);
        self::assertCount(1, $stele->sections[0]->entries);
        self::assertCount(1, $stele->sections[1]->entries);
        self::assertSame(SectionKind::Constraint, $stele->sections[0]->kind);
        self::assertSame(SectionKind::Intent, $stele->sections[1]->kind);
    }

    #[Test]
    public function full_stele_document(): void
    {
        $md = <<<'MD'
        > Phalanx context layer.
        > A PHP framework for async applications.

        ## Constraints

        ### No global state @!
        > when: always
        > .architecture .purity
        > <- src/Runtime/Boot.php

        Everything is injected. No static mutable state.

        ### Async-native @~
        > when: design, review
        > .async .swoole
        > <- .aimind/knowledge.md
        > ref: docs/async.md
        >   /fiber-model
        >   /event-loop

        Prefer non-blocking I/O. Use Swoole coroutines.

        ## Intents

        ### Developer ergonomics @!
        > when: api-design
        > .dx .api
        MD;

        $stele = $this->parser->parse($md);

        self::assertSame("Phalanx context layer.\nA PHP framework for async applications.", $stele->preamble);
        self::assertCount(2, $stele->sections);

        // Constraints section
        $constraints = $stele->sections[0];
        self::assertSame(SectionKind::Constraint, $constraints->kind);
        self::assertCount(2, $constraints->entries);

        $e1 = $constraints->entries[0];
        self::assertSame('No global state', $e1->name);
        self::assertSame(Epistemic::Certain, $e1->epistemic);
        self::assertSame(['always'], $e1->when);
        self::assertSame(['architecture', 'purity'], $e1->aspects);
        self::assertCount(1, $e1->pointers);
        self::assertSame('src/Runtime/Boot.php', $e1->pointers[0]->path);
        self::assertSame([], $e1->refs);
        self::assertSame('Everything is injected. No static mutable state.', $e1->body);

        $e2 = $constraints->entries[1];
        self::assertSame('Async-native', $e2->name);
        self::assertSame(Epistemic::Approximate, $e2->epistemic);
        self::assertSame(['design', 'review'], $e2->when);
        self::assertSame(['async', 'swoole'], $e2->aspects);
        self::assertCount(1, $e2->pointers);
        self::assertSame('.aimind/knowledge.md', $e2->pointers[0]->path);
        self::assertCount(1, $e2->refs);
        self::assertSame('docs/async.md', $e2->refs[0]->base);
        self::assertSame(['/fiber-model', '/event-loop'], $e2->refs[0]->subs);

        // Intents section
        $intents = $stele->sections[1];
        self::assertSame(SectionKind::Intent, $intents->kind);
        self::assertCount(1, $intents->entries);
        self::assertSame('Developer ergonomics', $intents->entries[0]->name);
        self::assertSame(['api-design'], $intents->entries[0]->when);
        self::assertSame(['dx', 'api'], $intents->entries[0]->aspects);
    }

    #[Test]
    public function entries_helper_flattens_all_sections(): void
    {
        $md = <<<'MD'
        ## Constraints

        ### Rule A @!

        ### Rule B @~

        ## Intents

        ### Goal C @!
        MD;

        $stele = $this->parser->parse($md);

        $entries = $stele->entries();
        self::assertCount(3, $entries);
        self::assertSame('Rule A', $entries[0]->name);
        self::assertSame('Rule B', $entries[1]->name);
        self::assertSame('Goal C', $entries[2]->name);
    }

    // ── Edge cases ──────────────────────────────────────────────────

    #[Test]
    public function when_with_empty_segments_are_filtered(): void
    {
        $md = <<<'MD'
        ## Constraints

        ### Trimmed when @!
        > when: boot, , deploy
        MD;

        $stele = $this->parser->parse($md);

        $entry = $stele->sections[0]->entries[0];
        self::assertSame(['boot', 'deploy'], $entry->when);
    }

    #[Test]
    public function aspects_require_lowercase_start(): void
    {
        $md = <<<'MD'
        ## Constraints

        ### Aspect casing @!
        > .valid-aspect .another1
        MD;

        $stele = $this->parser->parse($md);

        $entry = $stele->sections[0]->entries[0];
        self::assertSame(['valid-aspect', 'another1'], $entry->aspects);
    }

    #[Test]
    public function mixed_meta_and_content_stops_meta_at_first_non_blockquote(): void
    {
        $md = <<<'MD'
        ## Constraints

        ### Mixed content @!
        > when: always
        > .config
        Body starts here.
        > This is NOT meta — it's body.
        MD;

        $stele = $this->parser->parse($md);

        $entry = $stele->sections[0]->entries[0];
        self::assertSame(['always'], $entry->when);
        self::assertSame(['config'], $entry->aspects);
        self::assertStringContainsString('Body starts here.', $entry->body);
        self::assertStringContainsString('> This is NOT meta', $entry->body);
    }

    #[Test]
    public function bare_blockquote_in_meta_produces_empty_line(): void
    {
        $md = <<<'MD'
        ## Constraints

        ### Separated meta @!
        > when: always
        >
        > .config
        MD;

        $stele = $this->parser->parse($md);

        $entry = $stele->sections[0]->entries[0];
        self::assertSame(['always'], $entry->when);
        self::assertSame(['config'], $entry->aspects);
    }

    #[Test]
    public function section_heading_with_trailing_whitespace_is_matched(): void
    {
        $stele = $this->parser->parse("## Constraints   \n");

        self::assertCount(1, $stele->sections);
        self::assertSame(SectionKind::Constraint, $stele->sections[0]->kind);
    }

    #[Test]
    public function entry_heading_with_trailing_whitespace_is_matched(): void
    {
        $md = "## Constraints\n\n### Clean code @!   \n";

        $stele = $this->parser->parse($md);

        $entries = $stele->sections[0]->entries;
        self::assertCount(1, $entries);
        self::assertSame('Clean code', $entries[0]->name);
    }
}
