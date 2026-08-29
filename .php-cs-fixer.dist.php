<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        'array_syntax' => [ 'syntax' => 'short' ],
        'binary_operator_spaces' => true,
        'cast_spaces' => false,
        'combine_consecutive_unsets' => true,
        'concat_space' => [ 'spacing' => 'one' ],
        'linebreak_after_opening_tag' => true,
        'no_blank_lines_after_class_opening' => true,
        'no_blank_lines_after_phpdoc' => true,
        'no_extra_blank_lines' => true,
        'no_trailing_comma_in_singleline_array' => false,
        'no_whitespace_in_blank_line' => true,
        'no_spaces_around_offset' => true,
        'no_unused_imports' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_whitespace_before_comma_in_array' => true,
        'normalize_index_brace' => true,
        'phpdoc_indent' => true,
        'phpdoc_to_comment' => false,
        'phpdoc_trim' => true,
        'single_quote' => true,
        'ternary_operator_spaces' => true,
        'ternary_to_null_coalescing' => true,
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'no_break_comment' => false,
        'blank_line_before_statement' => false,
        'line_ending' => true,
        'single_blank_line_at_eof' => true,
        'short_scalar_cast' => true,
        'fully_qualified_strict_types' => true,
        'no_superfluous_phpdoc_tags' => true,
        'no_empty_phpdoc' => true
    ])
    ->setFinder((new Finder())->in(__DIR__))
;
