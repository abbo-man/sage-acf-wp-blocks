<?php

namespace App;

define('SAGE_ACF_GUTENBERG_BLOCKS_VERSION', '0.8.1');

// Check whether WordPress and ACF are available; bail if not.
if (
    !function_exists('acf_register_block_type') ||
    !function_exists('add_filter') ||
    !function_exists('add_action')
) {
    return;
}

// Add the default blocks location, 'views/blocks', via filter
add_filter('sage-acf-gutenberg-blocks-templates', function () {
    return ['views/blocks'];
});

/**
 * Create blocks based on templates found in Sage's "views/blocks" directory
 */
add_action('acf/init', function () {

    // Global $sage_error so we can throw errors in the typical sage manner
    global $sage_error;

    // Get an array of directories containing blocks
    $directories = apply_filters('sage-acf-gutenberg-blocks-templates', []);

    // Check whether ACF exists before continuing
    foreach ($directories as $directory) {
        $dir = isSage10() ? \Roots\resource_path($directory) : \locate_template($directory);

        // Sanity check whether the directory we're iterating over exists first
        if (!file_exists($dir)) {
            continue;
        }

        // Iterate over the directories provided and look for templates
        $template_directory = new \DirectoryIterator($dir);

        foreach ($template_directory as $template) {
            if (!$template->isDot() && !$template->isDir()) {
                // Strip the file extension to get the slug
                $slug = removeBladeExtension($template->getFilename());
                // If there is no slug (most likely because the filename does
                // not end with ".blade.php", move on to the next file.
                if (!$slug) {
                    continue;
                }

                // Get header info from the found template file(s)
                $file = "$dir/$slug.blade.php";
                $file_path = file_exists($file) ? $file : '';
                $file_headers = get_file_data($file_path, [
                    'title' => 'Title',
                    'description' => 'Description',
                    'category' => 'Category',
                    'icon' => 'Icon',
                    'keywords' => 'Keywords',
                    'mode' => 'Mode',
                    'align' => 'Align',
                    'post_types' => 'PostTypes',
                    'supports_align' => 'SupportsAlign',
                    'supports_anchor' => 'SupportsAnchor',
                    'supports_mode' => 'SupportsMode',
                    'supports_jsx' => 'SupportsInnerBlocks',
                    'supports_align_text' => 'SupportsAlignText',
                    'supports_align_content' => 'SupportsAlignContent',
                    'supports_full_height' => 'SupportsFullHeight',
                    'supports_multiple' => 'SupportsMultiple',
                    'enqueue_style'     => 'EnqueueStyle',
                    'enqueue_script'    => 'EnqueueScript',
                    'enqueue_assets'    => 'EnqueueAssets',
                    'parent' => 'Parent',
                ]);

                if (empty($file_headers['title'])) {
                    $sage_error(__('This block needs a title: ' . $dir . '/' . $template->getFilename(), 'sage'), __('Block title missing', 'sage'));
                }

                if (empty($file_headers['category'])) {
                    $sage_error(__('This block needs a category: ' . $dir . '/' . $template->getFilename(), 'sage'), __('Block category missing', 'sage'));
                }

                // Checks if dist contains this asset, then enqueues the dist version.
                if (!empty($file_headers['enqueue_style'])) {
                    checkAssetPath($file_headers['enqueue_style'], $slug);
                }

                if (!empty($file_headers['enqueue_script'])) {
                    checkAssetPath($file_headers['enqueue_script'], $slug);
                }

                // Set up block data for registration
                $data = [
                    'name' => $slug,
                    'title' => $file_headers['title'],
                    'description' => $file_headers['description'],
                    'category' => $file_headers['category'],
                    'icon' => $file_headers['icon'],
                    'keywords' => explode(' ', $file_headers['keywords']),
                    'mode' => $file_headers['mode'],
                    'align' => $file_headers['align'],
                    'render_callback'  => __NAMESPACE__ . '\\sage_blocks_callback',
                    'enqueue_style'   => $file_headers['enqueue_style'],
                    'enqueue_script'  => $file_headers['enqueue_script'],
                    'enqueue_assets'  => $file_headers['enqueue_assets'],
                    'example'  => array(
                        'attributes' => array(
                            'mode' => 'preview',
                        )
                    )
                ];

                // If the PostTypes header is set in the template, restrict this block to those types
                if (!empty($file_headers['post_types'])) {
                    $data['post_types'] = explode(' ', $file_headers['post_types']);
                }

                // If the SupportsAlign header is set in the template, restrict this block to those aligns
                if (!empty($file_headers['supports_align'])) {
                    $data['supports']['align'] = in_array($file_headers['supports_align'], array('true', 'false'), true) ? filter_var($file_headers['supports_align'], FILTER_VALIDATE_BOOLEAN) : explode(' ', $file_headers['supports_align']);
                }

                // If the SupportsMode header is set in the template, restrict this block mode feature
                if (!empty($file_headers['supports_anchor'])) {
                    $data['supports']['anchor'] = filter_var($file_headers['supports_anchor'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }

                // If the SupportsMode header is set in the template, restrict this block mode feature
                if (!empty($file_headers['supports_mode'])) {
                    $data['supports']['mode'] = filter_var($file_headers['supports_mode'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }

                // If the SupportsInnerBlocks header is set in the template, enable the InnerBlocks feature
                if (!empty($file_headers['supports_jsx'])) {
                    $data['supports']['jsx'] = filter_var($file_headers['supports_jsx'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }

                // If the SupportsAlignText header is set in the template, restrict this block align text feature
                if (!empty($file_headers['supports_align_text'])) {
                    $data['supports']['align_text'] = filter_var($file_headers['supports_align_text'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }

                // If the SupportsAlignContent header is set in the template, restrict this block align content feature
                if (!empty($file_headers['supports_align_content'])) {
                    $data['supports']['align_content'] = filter_var($file_headers['supports_align_content'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }

                // If the SupportsFullHeight header is set in the template, enables the full height button on the toolbar of a block
                if (!empty($file_headers['supports_full_height'])) {
                    $data['supports']['full_height'] = filter_var($file_headers['supports_full_height'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }

                // If the SupportsMultiple header is set in the template, restrict this block multiple feature
                if (!empty($file_headers['supports_multiple'])) {
                    $data['supports']['multiple'] = filter_var($file_headers['supports_multiple'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                }

                // If the Parent header is set in the template, restrict this block to specific parent blocks
                if (!empty($file_headers['parent'])) {
                    $data['parent'] = array_map(function ($name) {
                        return validateBlockName($name);
                    }, explode(' ', $file_headers['parent']));
                }

                // Register the block with ACF
                \acf_register_block_type(apply_filters("sage/blocks/$slug/register-data", $data));
            }
        }
    }
});

/**
 * Callback to register blocks
 */
function sage_blocks_callback($block, $content = '', $is_preview = false, $post_id = 0)
{
    // Set up the slug to be useful
    $slug  = str_replace('acf/', '', $block['name']);
    $block = array_merge(['className' => ''], $block);

    // Set up the block data
    $block['post_id'] = $post_id;
    $block['is_preview'] = $is_preview;
    $block['content'] = $content;
    $block['slug'] = $slug;
    $block['anchor'] = $block['anchor'] ?? '';
    // Send classes as array to filter for easy manipulation.
    $block['classes'] = [
        $slug,
        $block['className'],
        $block['is_preview'] ? 'is-preview' : null,
        'align' . $block['align']
    ];

    // Filter the block data.
    $block = apply_filters("sage/blocks/$slug/data", $block);

    // Join up the classes.
    $block['classes'] = implode(' ', array_filter($block['classes']));

    // Get the template directories.
    $directories = apply_filters('sage-acf-gutenberg-blocks-templates', []);

    foreach ($directories as $directory) {
        $dir = isSage10() ? \Roots\resource_path($directory) : \locate_template($directory);

        // Sanity check whether the directory we're iterating over exists first
        if (!file_exists($dir)) {
            continue;
        }

        $view = ltrim($directory, 'views/') . '/' . $slug;
        $templatePath = "{$dir}/{$slug}";

        if (!file_exists($templatePath . '.blade.php')) {
            continue;
        }

        if (isSage10() && \Roots\view()->exists($view)) {
            // Use Sage's view() function to echo the block and populate it with data
            echo \Roots\view($view, ['block' => $block]);
        } else {
            echo \App\template(locate_template("$directory/$slug"), ['block' => $block]);
        }
    }
}

/**
 * Function to strip the `.blade.php` from a blade filename
 */
function removeBladeExtension($filename)
{
    // Use a simple string manipulation if the filename ends with ".blade.php".
    if (substr($filename, -10) === '.blade.php') {
        return substr($filename, 0, -10);
    }
    // Return FALSE if the filename doesn't end with ".blade.php".
    return false;
}

/**
 * Checks asset path for specified asset.
 *
 * @param string &$path
 * @param string $block
 *
 * @return void
 */
function checkAssetPath(&$path, $block)
{
    if (isSage10()) {
        $useVite = class_exists('\Illuminate\Support\Facades\Vite');

        if ($useVite) {
            $path = \Illuminate\Support\Facades\Vite::asset($path);
            return;
        } elseif (function_exists('\Roots\bundle')) {
            $insertBlocks = function () use ($block, $path) {
                if (!has_block("acf/$block")) {
                    return;
                }

                \Roots\bundle($path)->enqueue();
            };

            add_action('wp_enqueue_scripts', $insertBlocks, 50);
            add_action('enqueue_block_editor_assets', $insertBlocks, 50);

            $path = ''; // Reset path
            return;
        }
    }

    if (preg_match("/^(styles|scripts)/", $path)) {
        $path = isSage10() ? \Roots\asset($path)->uri() : \App\asset_path($path);
    }
}

/**
 * Validates the format of a block name string
 *
 * @param string $name
 *
 * @return void|string
 */
function validateBlockName($name)
{
    global $sage_error;

    // A block name can only contain lowercase alphanumeric characters and dashes, and must begin with a letter.
    // NOTE: this cannot check whether a block is valid and registered (since others may be registered after this),
    // it just confirms the block name format is correct.
    if (!preg_match('/^[a-z]+\/[a-z][a-z0-9-]+$/', $name)) {
        $sage_error(__('Invalid parent block name format: ' . $name, 'sage'), __('Invalid parent block name', 'sage'));

        // Return NULL for invalid block names.
        return null;
    }

    return $name;
}

/**
 * Check if Sage 10 is used.
 *
 * @return bool
 */
function isSage10()
{
    return class_exists('Roots\Acorn\Application');
}
