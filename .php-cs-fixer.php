<?php
declare(strict_types=1);
use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Fixer\Internal\ConfigurableFixerTemplateFixer;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

return (new Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12'                       => true,

        // Override PSR-12 rulse
        // 'blank_line_between_import_groups' => true,

        'declare_strict_types'         => true,
        'strict_comparison'            => true,
        'strict_param'                 => true,
        'no_extra_blank_lines'         => true,
        'class_attributes_separation'  => true,
        'binary_operator_spaces'       => ['default' => 'align_single_space'],
        
        // Not in PSR-12
        // Cherry-picked from
        //  - @PHPCsFixer
        'align_multiline_comment'      => true,
        'array_indentation'            => true,
        'array_syntax'                 => ['syntax' => 'short'],
        'blank_line_before_statement'  => true,
        'class_attributes_separation'  => true,
        'clean_namespace'              => true,
        'combine_consecutive_issets'   => true,
        'combine_consecutive_unsets'   => true,
        'concat_space'                 => ['spacing' => 'none'],
        'declare_parentheses'         => true,
        'explicit_indirect_variable' => true,
        'explicit_string_variable'     => true,
        'global_namespace_import' => [
            'import_classes'       => true,
            'import_constants'     => null,
            'import_functions'     => null
        ],
        'include' => true,
        'increment_style' => ['style' => 'post'],
        'integer_literal_case' => true,
        'linebreak_after_opening_tag' => true,
        'magic_constant_casing' => true,
        'magic_method_casing' => true,
        'method_chaining_indentation' => true,
        'native_function_casing' => true,
        'native_type_declaration_casing' => true,
        'no_alias_language_construct_call' => true,
        'no_blank_lines_after_phpdoc' => true,
        'no_empty_comment' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'trim_array_spaces' => true,
        'types_spaces' => true,
        'whitespace_after_comma_in_array' => ['ensure_single_space' => true],
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],

        ])
    ->setFinder(
        Finder::create()
            ->in(__DIR__ . '/src')
            ->in(__DIR__ . '/tests')
            ->ignoreDotFiles(true)
            ->ignoreVCSIgnored(true)
            ->in(__DIR__)
            ->append([__DIR__.'/php-cs-fixer'])
    );
