<?php
/*
Plugin Name: Gary Wallage Industrial Performance
Description: The high-performance engine for the Gary Wallage Photography ecosystem. Consolidates bloat removal, LCP protection, bfcache optimization, and asset hardening into a single zero-overhead plugin.
Version: 1.2.0
Author: Gary David Wallage
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class GW_Performance_Engine {

    public function __construct() {
        // 1. Core Bloat Removal
        add_action( 'init', array( $this, 'nuke_wp_bloat' ) );
        
        // 2. LCP & Image Optimization
        add_action( 'wp_head', array( $this, 'preload_lcp_image' ), 1 );
        add_filter( 'wp_lazy_loading_enabled', array( $this, 'protect_lcp_images' ), 10, 3 );
        add_filter( 'wp_get_attachment_image_attributes', array( $this, 'add_lcp_priority' ), 10, 2 );
        add_filter( 'wp_get_attachment_image_attributes', array( $this, 'add_native_auto_sizes' ), 10, 2 );
        
        // 3. Script & Style Optimization
        add_filter( 'script_loader_tag', array( $this, 'defer_scripts' ), 10, 2 );
        add_filter( 'style_loader_src', array( $this, 'remove_query_strings' ), 10, 2 );
        add_filter( 'script_loader_src', array( $this, 'remove_query_strings' ), 10, 2 );

        // 4. Heartbeat Control (Saves Server CPU)
        add_action( 'init', array( $this, 'throttle_heartbeat' ), 1 );

        // 5. Lightweight Tracking (Replaces Google Site Kit)
        add_action( 'wp_head', array( $this, 'inject_tracking_codes' ), 1 );

        // 6. CLS & SEO Hardening (Fixes Layout Shift and Missing Alt Tags)
        add_filter( 'the_content', array( $this, 'anchor_image_dimensions' ), 99 );
        add_filter( 'wp_get_attachment_image_attributes', array( $this, 'ensure_image_alt_tags' ), 10, 2 );

        // 7. Modern Image Swapper (PNG/JPG -> WebP)
        add_filter( 'wp_get_attachment_image_src', array( $this, 'swap_with_webp' ), 10, 4 );

        // 8. Industrial HTML Cleaner & Facade Optimizer
        add_action( 'template_redirect', array( $this, 'start_cleaner_buffer' ), 1 );
        add_filter( 'the_content', array( $this, 'optimize_embed_facades' ), 20 );

        // 9. Unload Sync Bloat
        add_action( 'wp_enqueue_scripts', array( $this, 'unload_sync_bloat' ), 99 );

        // 10. Attachment Page Nullifier & bfcache Optimizer
        add_action( 'template_redirect', array( $this, 'redirect_attachment_pages' ), 1 );
        add_action( 'send_headers', array( $this, 'optimize_bfcache_headers' ) );

        // 11. Industrial Settings
        add_action( 'admin_menu', array( $this, 'add_settings_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // 12. SEO & Navigation (Migrated from Theme)
        add_action( 'wp_head', array( $this, 'inject_seo_meta' ), 5 );
        add_action( 'wp_head', array( $this, 'inject_seo_schema' ), 20 );

        // 13. Speculation Rules, Copyright Shield & Hardening
        add_action( 'wp_head', array( $this, 'inject_speculation_rules' ), 2 );
        add_action( 'wp_footer', array( $this, 'inject_copyright_shield' ), 99 );
        add_action( 'init', array( $this, 'harden_security' ) );
        add_filter( 'xmlrpc_enabled', array( $this, 'filter_xmlrpc_for_jetpack' ) );
        add_filter( 'xmlrpc_methods', array( $this, 'disable_xmlrpc_pingback' ) );
    }

    /**
     * Breadcrumbs: Minimalist editorial navigation (Public Helper)
     */
    public static function render_breadcrumbs() {
        if ( is_front_page() ) return;

        echo '<nav class="gw-breadcrumbs container" aria-label="Breadcrumb">';
        echo '<a href="' . esc_url( home_url( '/' ) ) . '">Home</a>';

        if ( is_category() || is_single() ) {
            echo '<span class="sep">✵</span>';
            $cats = get_the_category();
            if ( ! empty( $cats ) ) {
                echo '<a href="' . esc_url( get_category_link( $cats[0]->term_id ) ) . '">' . esc_html( $cats[0]->name ) . '</a>';
            }
        }

        if ( is_page() && ! is_front_page() ) {
            echo '<span class="sep">✵</span>';
            echo '<span class="current">' . esc_html( get_the_title() ) . '</span>';
        } elseif ( is_single() ) {
            echo '<span class="sep">✵</span>';
            echo '<span class="current">' . esc_html( get_the_title() ) . '</span>';
        } elseif ( is_archive() && ! is_category() ) {
            echo '<span class="sep">✵</span>';
            the_archive_title( '<span class="current">', '</span>' );
        } elseif ( is_search() ) {
            echo '<span class="sep">✵</span>';
            echo '<span class="current">Search Results</span>';
        }

        echo '</nav>';
    }

    /**
     * Enhancement 7: Speculation Rules API - Predictive Prefetch & Moderate Prerender
     */
    public function inject_speculation_rules() {
        if ( is_admin() ) return;
        
        $urls = array();
        $locations = get_nav_menu_locations();
        $menu = isset($locations['primary']) ? wp_get_nav_menu_object($locations['primary']) : null;
        if ($menu) {
            $items = wp_get_nav_menu_items($menu->term_id);
            if ($items) {
                foreach (array_slice($items, 0, 6) as $item) {
                    $urls[] = '"' . esc_url($item->url) . '"';
                }
            }
        }
        ?>
        <script type="speculationrules">
        {
          "prefetch": [
            {
              "source": "list",
              "urls": [<?php echo implode(',', $urls); ?>]
            }
          ],
          "prerender": [
            {
              "source": "document",
              "where": {
                "and": [
                  { "href_matches": "/*" },
                  { "not": { "href_matches": "/wp-admin/*" } },
                  { "not": { "href_matches": "/wp-login.php*" } },
                  { "not": { "href_matches": "/cart/*" } },
                  { "not": { "href_matches": "/checkout/*" } }
                ]
              },
              "eagerness": "moderate"
            }
          ]
        }
        </script>
        <?php
    }

    /**
     * Security Hardening: Strips legacy headers and disables XML-RPC.
     */
    public function harden_security() {
        if ( ! headers_sent() ) {
            header_remove( 'X-Pingback' );
        }
        add_filter( 'wp_headers', function( $headers ) {
            unset( $headers['X-Pingback'] );
            return $headers;
        });
    }

    /**
     * SEO Engine: Meta Injection
     */
    public function inject_seo_meta() {
        if ( is_front_page() || is_home() ) {
            $desc = get_bloginfo( 'description' );
        } elseif ( is_singular() ) {
            $desc = get_the_excerpt();
        } else {
            $desc = get_bloginfo( 'description' );
        }

        $desc = wp_strip_all_tags( $desc );
        echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( wp_get_document_title() ) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
        echo '<meta property="og:type" content="' . (is_singular() ? 'article' : 'website') . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( get_permalink() ) . '">' . "\n";
        if ( has_post_thumbnail() ) {
            echo '<meta property="og:image" content="' . esc_url( get_the_post_thumbnail_url( null, 'large' ) ) . '">' . "\n";
        }
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    }

    /**
     * SEO Engine: JSON-LD Schema
     */
    public function inject_seo_schema() {
        $schema = array();
        if ( is_front_page() ) {
            $schema = array(
                '@context' => 'https://schema.org',
                '@type'    => 'LocalBusiness',
                'name'     => get_bloginfo( 'name' ),
                'image'    => get_site_icon_url(),
                '@id'       => home_url( '/' ),
                'url'       => home_url( '/' ),
                'telephone' => get_theme_mod( 'footer_phone_display', '+44 7970 262 387' ),
                'address'   => array(
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => '63 Twineham Road',
                    'addressLocality' => 'Swindon',
                    'postalCode'      => 'SN25 2AG',
                    'addressCountry'  => 'GB'
                )
            );
        }
        if ( is_page_template( 'page-faq.php' ) ) {
            $post = get_post();
            if ( preg_match_all( '/<h3[^>]*>(.*?)<\/h3>\s*<p[^>]*>(.*?)<\/p>/si', $post->post_content, $matches ) ) {
                $questions = array();
                for ( $i = 0; $i < count( $matches[1] ); $i++ ) {
                    $questions[] = array(
                        '@type' => 'Question',
                        'name'  => wp_strip_all_tags( $matches[1][$i] ),
                        'acceptedAnswer' => array(
                            '@type' => 'Answer',
                            'text'  => wp_strip_all_tags( $matches[2][$i] )
                        )
                    );
                }
                if ( ! empty( $questions ) ) {
                    $schema = array(
                        '@context' => 'https://schema.org',
                        '@type'    => 'FAQPage',
                        'mainEntity' => $questions
                    );
                }
            }
        }
        if ( ! empty( $schema ) ) {
            echo '<script type="application/ld+json">' . json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>' . "\n";
        }
    }


    /**
     * Injects GSC and GA4 tags with zero bloat.
     * Replace the IDs below with your actual IDs.
     */
    public function inject_tracking_codes() {
        $options = get_option( 'gw_perf_settings' );
        $ga4_id = ! empty( $options['ga4_id'] ) ? $options['ga4_id'] : '';
        $gsc_id = ! empty( $options['gsc_id'] ) ? $options['gsc_id'] : '';

        // 1. Google Search Console (GSC)
        if ( $gsc_id ) {
            echo '<meta name="google-site-verification" content="' . esc_attr( $gsc_id ) . '" />' . "\n";
        }

        // 2. Google Analytics 4 (GA4)
        if ( $ga4_id ) {
            echo "<!-- GW Performance: Tracking -->\n";
            echo "<script async src='https://www.googletagmanager.com/gtag/js?id=" . esc_attr( $ga4_id ) . "'></script>\n";
            echo "<script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '" . esc_attr( $ga4_id ) . "', { 'anonymize_ip': true });
            </script>\n";
        }
    }

    /**
     * Nukes Emojis, Embeds, and RSD links that bloat the <head>
     */
    public function nuke_wp_bloat() {
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );
        remove_action( 'wp_head', 'rsd_link' );
        remove_action( 'wp_head', 'wlwmanifest_link' );
        remove_action( 'wp_head', 'wp_generator' );
        remove_action( 'wp_head', 'wp_shortlink_wp_head' );
        remove_action( 'wp_head', 'rest_output_link_wp_head' );
        remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
        remove_action( 'wp_head', 'wp_oembed_add_host_js' );
        
        // Disable jQuery Migrate (Huge Performance Win)
        add_action( 'wp_default_scripts', function( $scripts ) {
            if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
                $script = $scripts->registered['jquery'];
                if ( $script->deps ) {
                    $script->deps = array_diff( $script->deps, array( 'jquery-migrate' ) );
                }
            }
        });

        // Strip WooCommerce and Jetpack Render-Blocking Styles from Frontend
        add_action( 'wp_enqueue_scripts', function() {
            wp_dequeue_style( 'woocommerce-layout' );
            wp_dequeue_style( 'woocommerce-general' );
            wp_dequeue_style( 'woocommerce-smallscreen' );
            wp_dequeue_style( 'wc-blocks-vendors-style' );
            wp_dequeue_style( 'wc-blocks-style' );
            wp_dequeue_style( 'social-logos' );
            wp_dequeue_style( 'sharedaddy' );
        }, 99 );
    }

    /**
     * Protects the first few images from lazy-loading to ensure LCP is fast.
     */
    public function protect_lcp_images( $default, $context, $tag_name ) {
        if ( is_admin() ) return $default;
        
        static $count = 0;
        if ( 'img' === $tag_name ) {
            $count++;
            if ( $count <= 6 ) return false; // First 6 images load immediately for viewport
        }
        return $default;
    }

    /**
     * Injects fetchpriority="high" for the first image found on the page.
     */
    public function add_lcp_priority( $attrs, $attachment ) {
        static $high_priority_set = false;
        if ( ! is_admin() && ! $high_priority_set ) {
            $attrs['fetchpriority'] = 'high';
            $high_priority_set = true;
        }
        return $attrs;
    }

    /**
     * Automatically defers non-critical scripts to improve Time to Interactive.
     */
    public function defer_scripts( $tag, $handle ) {
        // Do not defer jQuery or critical theme scripts
        $exclude = array( 'jquery', 'gary-wedding-main', 'gw-hero-slider' );
        if ( is_admin() || in_array( $handle, $exclude ) ) return $tag;
        
        return str_replace( ' src', ' defer="defer" src', $tag );
    }

    /**
     * DISABLED: Stripping query strings prevents cache busting.
     */
    public function remove_query_strings( $src ) {
        return $src;
    }

    /**
     * Throttles the WordPress Heartbeat to save server resources.
     */
    public function throttle_heartbeat() {
        wp_deregister_script( 'heartbeat' );
    }

    /**
     * Fixes CLS (Cumulative Layout Shift) by ensuring all images in content 
     * have width and height attributes.
     */
    public function anchor_image_dimensions( $content ) {
        if ( ! $content ) return $content;

        // Regex to find images and check for width/height
        $content = preg_replace_callback( '/<img[^>]+>/i', function( $match ) {
            $img = $match[0];
            
            // If already has both, leave it
            if ( strpos( $img, 'width=' ) !== false && strpos( $img, 'height=' ) !== false ) {
                return $img;
            }

            // Try to extract ID from class or src
            if ( preg_match( '/wp-image-([0-9]+)/i', $img, $id_match ) ) {
                $id = $id_match[1];
                $meta = wp_get_attachment_metadata( $id );
                if ( $meta && isset( $meta['width'], $meta['height'] ) ) {
                    if ( strpos( $img, 'width=' ) === false ) {
                        $img = str_replace( '<img', '<img width="' . $meta['width'] . '"', $img );
                    }
                    if ( strpos( $img, 'height=' ) === false ) {
                        $img = str_replace( '<img', '<img height="' . $meta['height'] . '"', $img );
                    }
                }
            }
            return $img;
        }, $content );

        return $content;
    }

    /**
     * Ensures all images have Alt tags for SEO and Accessibility.
     * Uses the attachment title or page title as a fallback.
     */
    public function ensure_image_alt_tags( $attr, $attachment ) {
        if ( empty( $attr['alt'] ) ) {
            $title = get_the_title( $attachment->ID );
            if ( ! $title ) {
                $title = get_the_title(); // Fallback to Page Title
            }
            $attr['alt'] = esc_attr( $title );
        }
        return $attr;
    }

    /**
     * Redundant CSS injection removed.
     */

    /**
     * Modern Image Swapper: Intercepts image URLs and swaps them for WebP siblings
     * if they exist, saving massive bandwidth with zero performance hit.
     */
    public function swap_with_webp( $image, $attachment_id, $size, $icon ) {
        if ( is_admin() || ! $image ) return $image;

        $url = $image[0];
        $path = str_replace( content_url(), WP_CONTENT_DIR, $url );
        $webp_path = preg_replace( '/\.(png|jpe?g)$/i', '.webp', $path );

        // Only swap if the WebP file actually exists on the disk
        if ( file_exists( $webp_path ) ) {
            $image[0] = preg_replace( '/\.(png|jpe?g)$/i', '.webp', $url );
        }

        return $image;
    }

    /**
     * Starts the output buffer to allow for surgical HTML cleaning.
     */
    public function start_cleaner_buffer() {
        if ( ! is_admin() ) {
            ob_start( array( $this, 'industrial_html_cleaner' ) );
        }
    }

    /**
     * Surgically removes broken legacy links and ghost plugin references 
     * from the final HTML output before it reaches the browser.
     */
    public function industrial_html_cleaner( $html ) {
        if ( ! $html ) return $html;

        // Remove <link> tags containing broken paths (removed stale your-theme check)
        $html = preg_replace( '/<link[^>]+(?:_jb_static|image-prioritizer)[^>]*>/i', '<!-- Removed Ghost Link -->', $html );

        // Remove <script> tags containing broken paths (removed stale your-theme check)
        $html = preg_replace( '/<script[^>]+(?:_jb_static|image-prioritizer)[^>]*>.*?<\/script>/i', '<!-- Removed Ghost Script -->', $html );

        return $html;
    }

    /**
     * Unloads scripts from sync plugins that are not needed by frontend visitors.
     */
    public function unload_sync_bloat() {
        if ( is_admin() ) return;
        
        wp_dequeue_script( 'lr-wp-bridge-js' );
        wp_dequeue_style( 'lr-wp-bridge-css' );
    }

    /**
     * --- INDUSTRIAL SETTINGS PAGE ---
     */
    public function add_settings_menu() {
        add_options_page( 'GW Performance', 'GW Performance', 'manage_options', 'gw-perf-settings', array( $this, 'render_settings_page' ) );
    }

    public function register_settings() {
        register_setting( 'gw_perf_settings_group', 'gw_perf_settings' );
    }

    public function render_settings_page() {
        $options = get_option( 'gw_perf_settings' );
        ?>
        <div class="wrap">
            <h1>Industrial Performance Settings</h1>
            <div class="notice notice-info" style="border-left-color: #C5A059;">
                <p><strong>Industrial Guide:</strong> This plugin replaces Google Site Kit and Jetpack Boost with zero-overhead code.</p>
                <ul>
                    <li><strong>GA4 ID:</strong> Find this in Google Analytics under <em>Admin > Data Streams > [Your Stream]</em>. It starts with <strong>G-</strong>.</li>
                    <li><strong>Search Console ID:</strong> Paste only the <code>content="..."</code> value from your Google HTML meta tag.</li>
                    <li><strong>Performance:</strong> All legacy tracking bloat has been removed. These keys are injected using high-priority, lightweight scripts.</li>
                </ul>
            </div>
            <form method="post" action="options.php">
                <?php settings_fields( 'gw_perf_settings_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">GA4 Measurement ID</th>
                        <td>
                            <input type="text" name="gw_perf_settings[ga4_id]" value="<?php echo esc_attr( $options['ga4_id'] ?? '' ); ?>" placeholder="G-XXXXXXXXXX" class="regular-text" />
                            <p class="description">Your Google Analytics 4 ID.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Search Console ID</th>
                        <td>
                            <input type="text" name="gw_perf_settings[gsc_id]" value="<?php echo esc_attr( $options['gsc_id'] ?? '' ); ?>" placeholder="Verification Content String" class="regular-text" />
                            <p class="description">The 'content' value from your GSC meta tag.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Smart XML-RPC Filter: Allow Jetpack/Automattic sync probes while blocking untrusted brute force.
     */
    public function filter_xmlrpc_for_jetpack( $enabled ) {
        // Always allow if Jetpack is verifying or syncing
        if ( isset( $_SERVER['HTTP_USER_AGENT'] ) && strpos( $_SERVER['HTTP_USER_AGENT'], 'Jetpack' ) !== false ) {
            return true;
        }
        // Allow if request contains Jetpack signature or token
        if ( isset( $_GET['jetpack'] ) || isset( $_SERVER['HTTP_X_JETPACK_SIGNATURE'] ) ) {
            return true;
        }
        return false;
    }

    /**
     * Disable dangerous XML-RPC pingback DDoS vectors while preserving standard API functions.
     */
    public function disable_xmlrpc_pingback( $methods ) {
        unset( $methods['pingback.ping'] );
        unset( $methods['pingback.extensions.getPingbacks'] );
        return $methods;
    }

    /**
     * Enhancement 1: Dynamic <head> LCP Image Preloader
     */
    public function preload_lcp_image() {
        if ( is_admin() ) return;

        $preload_url = '';

        if ( is_singular() && has_post_thumbnail() ) {
            $thumb_id = get_post_thumbnail_id();
            $src = wp_get_attachment_image_src( $thumb_id, 'large' );
            if ( $src && ! empty( $src[0] ) ) {
                $preload_url = $src[0];
            }
        } elseif ( is_front_page() ) {
            $locations = get_nav_menu_locations();
            $menu = isset($locations['primary']) ? wp_get_nav_menu_object($locations['primary']) : null;
            if ( $menu ) {
                $items = wp_get_nav_menu_items( $menu->term_id );
                if ( $items && ! empty( $items[0]->object_id ) ) {
                    $hero_thumb_id = get_post_thumbnail_id( $items[0]->object_id );
                    if ( $hero_thumb_id ) {
                        $src = wp_get_attachment_image_src( $hero_thumb_id, 'large' );
                        if ( $src && ! empty( $src[0] ) ) {
                            $preload_url = $src[0];
                        }
                    }
                }
            }
        }

        if ( $preload_url ) {
            $is_webp = (bool) preg_match( '/\.webp$/i', $preload_url );
            $type_attr = $is_webp ? ' type="image/webp"' : '';
            echo '<link rel="preload" as="image" href="' . esc_url( $preload_url ) . '"' . $type_attr . ' fetchpriority="high" />' . "\n";
        }
    }

    /**
     * Enhancement 2: Native HTML5 sizes="auto" attribute for responsive lazy images
     */
    public function add_native_auto_sizes( $attr, $attachment ) {
        if ( is_admin() ) return $attr;
        
        if ( isset( $attr['loading'] ) && 'lazy' === $attr['loading'] ) {
            if ( ! empty( $attr['sizes'] ) && strpos( $attr['sizes'], 'auto' ) === false ) {
                $attr['sizes'] = 'auto, ' . $attr['sizes'];
            }
        }
        return $attr;
    }

    /**
     * Enhancement 4: Lazy Embed Facades for YouTube/Vimeo/Google Maps
     */
    public function optimize_embed_facades( $content ) {
        if ( ! $content || is_admin() ) return $content;
        $content = preg_replace( '/<iframe(?![^>]*\bloading=)([^>]*)>/i', '<iframe loading="lazy" fetchpriority="low"$1>', $content );
        return $content;
    }

    /**
     * Enhancement 5: Attachment Page Nullifier (Prevents empty attachment page indexing)
     */
    public function redirect_attachment_pages() {
        if ( is_attachment() ) {
            global $post;
            if ( ! empty( $post->post_parent ) ) {
                wp_safe_redirect( get_permalink( $post->post_parent ), 301 );
            } else {
                wp_safe_redirect( home_url( '/' ), 301 );
            }
            exit;
        }
    }

    /**
     * Enhancement 3: Back/Forward Cache (bfcache) Header Optimization
     */
    public function optimize_bfcache_headers() {
        if ( ! is_user_logged_in() && ! is_admin() ) {
            header_remove( 'Pragma' );
        }
    }

    /**
     * Enhancement 6: Zero-Overhead Lightweight Copyright Shield for Photography
     */
    public function inject_copyright_shield() {
        if ( is_admin() || is_user_logged_in() ) return;
        ?>
        <script id="gw-copyright-shield">
        document.addEventListener('contextmenu', function(e) {
            if (e.target.closest('.wp-block-image, .portfolio-gallery, .hero-carousel, .gw-gallery-grid, .custom-logo, .image-frame')) {
                e.preventDefault();
            }
        }, { passive: false });
        document.addEventListener('dragstart', function(e) {
            if (e.target.closest('.wp-block-image img, .portfolio-gallery img, .hero-carousel img, .gw-gallery-grid img, .image-frame img')) {
                e.preventDefault();
            }
        }, { passive: false });
        </script>
        <?php
    }
}

new GW_Performance_Engine();
