<?php
/*
Plugin Name: Gary Wallage Industrial Performance
Description: The high-performance engine for the Gary Wallage Photography ecosystem. Consolidates bloat removal, LCP protection, bfcache optimization, and asset hardening into a single zero-overhead plugin.
Version: 1.4.0
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
        add_action( 'wp_enqueue_scripts', array( $this, 'unload_sync_bloat' ), 1000 );

        // 10. Attachment Page Nullifier & bfcache Optimizer
        add_action( 'template_redirect', array( $this, 'redirect_attachment_pages' ), 1 );
        add_action( 'send_headers', array( $this, 'optimize_bfcache_headers' ) );

        // 11. Industrial Settings
        add_action( 'admin_menu', array( $this, 'add_settings_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // 12. SEO & Navigation (Migrated from Theme & Enhanced Bespoke SEO)
        add_action( 'wp_head', array( $this, 'inject_seo_meta' ), 5 );
        add_action( 'wp_head', array( $this, 'inject_seo_schema' ), 20 );
        add_filter( 'pre_get_document_title', array( $this, 'filter_seo_title' ), 15 );
        add_action( 'add_meta_boxes', array( $this, 'add_seo_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_seo_meta_box' ) );
        add_action( 'admin_post_gw_transfer_aioseo', array( $this, 'handle_transfer_aioseo' ) );

        // 13. Speculation Rules, Copyright Shield & Hardening
        add_action( 'wp_head', array( $this, 'inject_speculation_rules' ), 2 );
        add_action( 'wp_footer', array( $this, 'inject_copyright_shield' ), 99 );
        add_action( 'init', array( $this, 'harden_security' ) );
        add_filter( 'xmlrpc_enabled', array( $this, 'filter_xmlrpc_for_jetpack' ) );
        add_filter( 'xmlrpc_methods', array( $this, 'disable_xmlrpc_pingback' ) );

        // 14. Font Performance (display=swap & preconnect)
        add_filter( 'style_loader_src', array( $this, 'add_font_display_swap' ), 10, 2 );
        add_filter( 'wp_resource_hints', array( $this, 'add_font_resource_hints' ), 10, 2 );

        // 14. Dominant Color Image Placeholders (replaces dominant-color-images plugin)
        add_filter( 'wp_generate_attachment_metadata', array( $this, 'compute_dominant_color_metadata' ), 10, 2 );
        add_filter( 'wp_get_attachment_image_attributes', array( $this, 'apply_dominant_color_attributes' ), 10, 2 );
        add_action( 'wp_enqueue_scripts', array( $this, 'inject_dominant_color_style' ) );
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
     * Bespoke SEO Engine: Meta & Social Tag Injection
     */
    public function inject_seo_meta() {
        // Prevent duplicate meta description if Jetpack SEO module is active
        $has_jetpack_seo = class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'seo-tools' );
        if ( $has_jetpack_seo ) {
            return;
        }

        $desc = '';
        $custom_title = '';
        $canonical = '';
        $noindex = false;

        if ( is_front_page() ) {
            if ( 'page' === get_option( 'show_on_front' ) && ( $front_id = get_option( 'page_on_front' ) ) ) {
                $desc = get_post_meta( $front_id, '_gw_seo_description', true );
                $custom_title = get_post_meta( $front_id, '_gw_seo_title', true );
                $canonical = get_post_meta( $front_id, '_gw_seo_canonical', true );
                $noindex = (bool) get_post_meta( $front_id, '_gw_seo_noindex', true );
            }
            if ( empty( $desc ) ) {
                $desc = get_bloginfo( 'description' );
            }
        } elseif ( is_singular() ) {
            $post_id = get_the_ID();
            $desc = get_post_meta( $post_id, '_gw_seo_description', true );
            if ( empty( $desc ) ) {
                $desc = get_the_excerpt();
            }
            $custom_title = get_post_meta( $post_id, '_gw_seo_title', true );
            $canonical = get_post_meta( $post_id, '_gw_seo_canonical', true );
            $noindex = (bool) get_post_meta( $post_id, '_gw_seo_noindex', true );
        } elseif ( is_home() || is_archive() || is_category() || is_tag() ) {
            $desc = get_the_archive_description();
            if ( empty( $desc ) ) {
                $desc = get_bloginfo( 'description' );
            }
        } else {
            $desc = get_bloginfo( 'description' );
        }

        $desc = wp_strip_all_tags( (string) $desc );
        if ( $desc ) {
            echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
        }

        if ( $noindex ) {
            echo '<meta name="robots" content="noindex, follow">' . "\n";
        }

        if ( $canonical ) {
            echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
        }

        $og_title = ! empty( $custom_title ) ? $custom_title : wp_get_document_title();
        echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '">' . "\n";
        if ( $desc ) {
            echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
        }
        echo '<meta property="og:type" content="' . ( is_singular() ? 'article' : 'website' ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( get_permalink() ) . '">' . "\n";
        
        $og_img = '';
        if ( has_post_thumbnail() ) {
            $og_img = get_the_post_thumbnail_url( null, 'large' );
        } else {
            $options = get_option( 'gw_perf_settings' );
            if ( ! empty( $options['default_og_image'] ) ) {
                $og_img = $options['default_og_image'];
            } elseif ( has_custom_logo() ) {
                $og_img = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
            }
        }
        if ( $og_img ) {
            echo '<meta property="og:image" content="' . esc_url( $og_img ) . '">' . "\n";
        }

        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '">' . "\n";
        if ( $desc ) {
            echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
        }
        if ( $og_img ) {
            echo '<meta name="twitter:image" content="' . esc_url( $og_img ) . '">' . "\n";
        }
    }

    /**
     * Bespoke SEO Engine: JSON-LD Schema
     */
    public function inject_seo_schema() {
        $options = get_option( 'gw_perf_settings', array() );
        $schema = array();
        if ( is_front_page() ) {
            $schema_type = ! empty( $options['schema_type'] ) ? $options['schema_type'] : 'LocalBusiness';
            $biz_name    = ! empty( $options['business_name'] ) ? $options['business_name'] : get_bloginfo( 'name' );
            $telephone   = ! empty( $options['telephone'] ) ? $options['telephone'] : get_theme_mod( 'footer_phone_display', '' );

            $logo_url = '';
            if ( has_custom_logo() ) {
                $logo_url = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
            } elseif ( get_site_icon_url() ) {
                $logo_url = get_site_icon_url();
            }

            $schema = array(
                '@context' => 'https://schema.org',
                '@type'    => $schema_type,
                'name'     => $biz_name,
                '@id'      => home_url( '/' ),
                'url'      => home_url( '/' ),
            );
            if ( $logo_url ) {
                $schema['image'] = $logo_url;
            }
            if ( $telephone ) {
                $schema['telephone'] = $telephone;
            }

            $street   = ! empty( $options['street_address'] ) ? $options['street_address'] : '';
            $city     = ! empty( $options['locality'] ) ? $options['locality'] : '';
            $postcode = ! empty( $options['postal_code'] ) ? $options['postal_code'] : '';
            $country  = ! empty( $options['country'] ) ? $options['country'] : 'GB';

            if ( $street || $postcode || $city ) {
                $addr = array( '@type' => 'PostalAddress' );
                if ( $street )   $addr['streetAddress']   = $street;
                if ( $city )     $addr['addressLocality'] = $city;
                if ( $postcode ) $addr['postalCode']      = $postcode;
                if ( $country )  $addr['addressCountry']  = $country;
                $schema['address'] = $addr;
            }

            if ( ! empty( $options['social_profiles'] ) && is_array( $options['social_profiles'] ) ) {
                $schema['sameAs'] = array_values( array_filter( $options['social_profiles'] ) );
            }
        } elseif ( is_singular( 'post' ) ) {
            $schema = array(
                '@context' => 'https://schema.org',
                '@type'    => 'Article',
                'headline' => get_the_title(),
                'datePublished' => get_the_date( 'c' ),
                'dateModified'  => get_the_modified_date( 'c' ),
                'mainEntityOfPage' => get_permalink(),
                'author' => array(
                    '@type' => 'Person',
                    'name'  => get_the_author(),
                ),
            );
            if ( has_post_thumbnail() ) {
                $schema['image'] = get_the_post_thumbnail_url( null, 'large' );
            }
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
                    $faq_schema = array(
                        '@context' => 'https://schema.org',
                        '@type'    => 'FAQPage',
                        'mainEntity' => $questions
                    );
                    echo '<script type="application/ld+json">' . json_encode( $faq_schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>' . "\n";
                }
            }
        }
        if ( ! empty( $schema ) ) {
            echo '<script type="application/ld+json">' . json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>' . "\n";
        }
    }

    /**
     * Bespoke SEO: Document Title Filter
     */
    public function filter_seo_title( $title ) {
        if ( is_singular() ) {
            $post_id = get_the_ID();
            $custom_title = get_post_meta( $post_id, '_gw_seo_title', true );
            if ( ! empty( $custom_title ) ) {
                return $custom_title;
            }
        } elseif ( is_front_page() && 'page' === get_option( 'show_on_front' ) ) {
            $front_id = get_option( 'page_on_front' );
            $custom_title = get_post_meta( $front_id, '_gw_seo_title', true );
            if ( ! empty( $custom_title ) ) {
                return $custom_title;
            }
        }
        return $title;
    }

    /**
     * Bespoke SEO: Meta Box for Page/Post Editor
     */
    public function add_seo_meta_box() {
        $post_types = get_post_types( array( 'public' => true ), 'names' );
        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'gw_seo_meta_box',
                'GW Bespoke SEO & Social',
                array( $this, 'render_seo_meta_box' ),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    public function render_seo_meta_box( $post ) {
        wp_nonce_field( 'gw_seo_save_meta', 'gw_seo_meta_nonce' );
        $title     = get_post_meta( $post->ID, '_gw_seo_title', true );
        $desc      = get_post_meta( $post->ID, '_gw_seo_description', true );
        $canonical = get_post_meta( $post->ID, '_gw_seo_canonical', true );
        $noindex   = get_post_meta( $post->ID, '_gw_seo_noindex', true );
        ?>
        <style>
            .gw-seo-box { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
            .gw-seo-field { margin-bottom: 15px; }
            .gw-seo-field label { display: block; font-weight: 600; margin-bottom: 5px; color: #1d2327; }
            .gw-seo-field input[type="text"], .gw-seo-field textarea { width: 100%; border: 1px solid #8c8f94; border-radius: 4px; padding: 6px 8px; box-sizing: border-box; }
            .gw-seo-field textarea { min-height: 70px; }
            .gw-seo-hint { font-size: 12px; color: #646970; margin-top: 4px; margin-bottom: 0; }
            .gw-seo-preview { background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; padding: 12px; margin-top: 15px; }
            .gw-seo-preview-title { font-size: 18px; color: #1a0dab; line-height: 1.3; text-decoration: none; display: block; margin-bottom: 3px; font-weight: 400; }
            .gw-seo-preview-url { font-size: 13px; color: #006621; line-height: 1.3; margin-bottom: 4px; }
            .gw-seo-preview-desc { font-size: 13px; color: #4d5156; line-height: 1.4; }
        </style>
        <div class="gw-seo-box">
            <div class="gw-seo-field">
                <label for="gw_seo_title">SEO Title (Overrides document title)</label>
                <input type="text" id="gw_seo_title" name="gw_seo_title" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php echo esc_attr( get_the_title( $post ) ); ?>" maxlength="100" />
                <p class="gw-seo-hint">Recommended: 50–60 characters.</p>
            </div>
            <div class="gw-seo-field">
                <label for="gw_seo_description">Meta Description</label>
                <textarea id="gw_seo_description" name="gw_seo_description" maxlength="300"><?php echo esc_textarea( $desc ); ?></textarea>
                <p class="gw-seo-hint">Recommended: 120–160 characters. Search snippet for Google and social previews.</p>
            </div>
            <div class="gw-seo-field">
                <label for="gw_seo_canonical">Canonical URL (Optional override)</label>
                <input type="text" id="gw_seo_canonical" name="gw_seo_canonical" value="<?php echo esc_attr( $canonical ); ?>" placeholder="<?php echo esc_url( get_permalink( $post ) ); ?>" />
            </div>
            <div class="gw-seo-field">
                <label>
                    <input type="checkbox" name="gw_seo_noindex" value="1" <?php checked( $noindex, '1' ); ?> />
                    Prevent search engines from indexing this page (noindex)
                </label>
            </div>
            <div class="gw-seo-preview">
                <strong style="font-size: 11px; color: #50575e; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">Google Search Preview</strong>
                <span class="gw-seo-preview-title"><?php echo esc_html( $title ? $title : get_the_title( $post ) . ' - ' . get_bloginfo( 'name' ) ); ?></span>
                <div class="gw-seo-preview-url"><?php echo esc_url( $canonical ? $canonical : get_permalink( $post ) ); ?></div>
                <div class="gw-seo-preview-desc"><?php echo esc_html( $desc ? $desc : ( has_excerpt( $post ) ? get_the_excerpt( $post ) : get_bloginfo( 'description' ) ) ); ?></div>
            </div>
        </div>
        <?php
    }

    public function save_seo_meta_box( $post_id ) {
        if ( ! isset( $_POST['gw_seo_meta_nonce'] ) || ! wp_verify_nonce( $_POST['gw_seo_meta_nonce'], 'gw_seo_save_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        if ( isset( $_POST['gw_seo_title'] ) ) {
            update_post_meta( $post_id, '_gw_seo_title', sanitize_text_field( wp_unslash( $_POST['gw_seo_title'] ) ) );
        }
        if ( isset( $_POST['gw_seo_description'] ) ) {
            update_post_meta( $post_id, '_gw_seo_description', sanitize_textarea_field( wp_unslash( $_POST['gw_seo_description'] ) ) );
        }
        if ( isset( $_POST['gw_seo_canonical'] ) ) {
            update_post_meta( $post_id, '_gw_seo_canonical', esc_url_raw( wp_unslash( $_POST['gw_seo_canonical'] ) ) );
        }
        update_post_meta( $post_id, '_gw_seo_noindex', ! empty( $_POST['gw_seo_noindex'] ) ? '1' : '0' );
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
            $attrs['loading'] = 'eager';
            $high_priority_set = true;
        }
        // Mobile-responsive logo sizing (ensures 70px mobile display gets matched thumbnail)
        if ( isset( $attrs['class'] ) && strpos( $attrs['class'], 'custom-logo' ) !== false ) {
            $attrs['sizes'] = '(max-width: 768px) 70px, 250px';
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
            // If missing width or height, attempt recovery
            if ( strpos( $img, 'width=' ) === false || strpos( $img, 'height=' ) === false ) {
                $w = null;
                $h = null;
                // Try to extract ID from class or src
                if ( preg_match( '/wp-image-([0-9]+)/i', $img, $id_match ) ) {
                    $id = $id_match[1];
                    $meta = wp_get_attachment_metadata( $id );
                    if ( $meta && isset( $meta['width'], $meta['height'] ) ) {
                        $w = $meta['width'];
                        $h = $meta['height'];
                    }
                }
                // Try from dimension pattern in file name (e.g. -600x428.webp)
                if ( ( ! $w || ! $h ) && preg_match( '/-([0-9]{2,4})x([0-9]{2,4})\.(?:webp|jpg|jpeg|png|avif)/i', $img, $dim_match ) ) {
                    $w = $dim_match[1];
                    $h = $dim_match[2];
                }

                if ( $w && strpos( $img, 'width=' ) === false ) {
                    $img = str_replace( '<img', '<img width="' . (int)$w . '"', $img );
                }
                if ( $h && strpos( $img, 'height=' ) === false ) {
                    $img = str_replace( '<img', '<img height="' . (int)$h . '"', $img );
                }
            }

            // Ensure alt attribute is present and non-empty
            if ( ! preg_match( '/alt=["\']([^"\']+)["\']/i', $img ) ) {
                $fallback_alt = esc_attr( get_bloginfo( 'name' ) );
                if ( preg_match( '/alt=["\']\s*["\']/i', $img ) ) {
                    $img = preg_replace( '/alt=["\']\s*["\']/i', 'alt="' . $fallback_alt . '"', $img );
                } else {
                    $img = str_replace( '<img', '<img alt="' . $fallback_alt . '"', $img );
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
        $alt = isset( $attr['alt'] ) ? trim( (string) $attr['alt'] ) : '';
        if ( empty( $alt ) ) {
            $alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
            if ( empty( $alt ) ) {
                $alt = get_the_title( $attachment->ID );
            }
            if ( empty( $alt ) ) {
                $alt = get_the_title();
            }
            if ( empty( $alt ) ) {
                $alt = get_bloginfo( 'name' );
            }
            $attr['alt'] = esc_attr( $alt );
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
     * from the final HTML output, swaps images for WebP siblings, and anchors dimensions.
     */
    public function industrial_html_cleaner( $html ) {
        if ( ! $html || is_admin() ) return $html;

        // 1. Remove <link> and <script> tags containing broken legacy paths
        $html = preg_replace( '/<link[^>]+(?:_jb_static|image-prioritizer)[^>]*>/i', '<!-- Removed Ghost Link -->', $html );
        $html = preg_replace( '/<script[^>]+(?:_jb_static|image-prioritizer)[^>]*>.*?<\/script>/i', '<!-- Removed Ghost Script -->', $html );

        // 2. Automated WebP Replacement across output buffer (src, srcset, data-image, etc.)
        $html = $this->rewrite_html_images_to_webp( $html );

        // 3. Anchor image dimensions and alt attributes across all <img> tags (fixing CLS & accessibility)
        $html = $this->enforce_html_image_attributes( $html );

        return $html;
    }

    /**
     * Intercepts upload image URLs across the entire HTML output buffer and swaps them for WebP
     * siblings when present on disk.
     */
    public function rewrite_html_images_to_webp( $html ) {
        static $checked_urls = array();

        $uploads = wp_upload_dir();
        $base_url = $uploads['baseurl'];
        $base_dir = $uploads['basedir'];
        $regex_base = preg_quote( $base_url, '/' );

        return preg_replace_callback( '/' . $regex_base . '\/([^\s"\'<>]+\.(?:png|jpe?g))/i', function( $m ) use ( &$checked_urls, $base_url, $base_dir ) {
            $full_url = $m[0];
            $rel_path = $m[1];

            if ( isset( $checked_urls[ $full_url ] ) ) {
                return $checked_urls[ $full_url ];
            }

            $base_rel = preg_replace( '/\.(?:png|jpe?g)$/i', '', $rel_path );
            $webp_rel1 = $base_rel . '.webp';
            $webp_rel2 = $rel_path . '.webp';

            if ( file_exists( $base_dir . '/' . $webp_rel1 ) ) {
                $checked_urls[ $full_url ] = $base_url . '/' . $webp_rel1;
            } elseif ( file_exists( $base_dir . '/' . $webp_rel2 ) ) {
                $checked_urls[ $full_url ] = $base_url . '/' . $webp_rel2;
            } else {
                $checked_urls[ $full_url ] = $full_url;
            }

            return $checked_urls[ $full_url ];
        }, $html );
    }

    /**
     * Enforces explicit width, height, and fallback alt attributes on all <img> tags in the HTML buffer.
     */
    public function enforce_html_image_attributes( $html ) {
        static $dim_cache = array();
        $uploads = wp_upload_dir();
        $base_url = $uploads['baseurl'];
        $base_dir = $uploads['basedir'];

        return preg_replace_callback( '/<img\b([^>]*)>/i', function( $m ) use ( &$dim_cache, $base_url, $base_dir ) {
            $tag = $m[0];

            $has_w = (bool) preg_match( '/\bwidth=["\']?[0-9]+/i', $tag );
            $has_h = (bool) preg_match( '/\bheight=["\']?[0-9]+/i', $tag );

            if ( ! $has_w || ! $has_h ) {
                $w = null;
                $h = null;

                // Dimension from filename pattern: e.g. -300x176.webp or -500x380.jpeg
                if ( preg_match( '/-([0-9]{2,4})x([0-9]{2,4})\.(?:webp|png|jpe?g|avif)/i', $tag, $dm ) ) {
                    $w = (int) $dm[1];
                    $h = (int) $dm[2];
                } elseif ( preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $tag, $sm ) ) {
                    $src = $sm[1];
                    if ( isset( $dim_cache[ $src ] ) ) {
                        $w = $dim_cache[ $src ]['w'];
                        $h = $dim_cache[ $src ]['h'];
                    } elseif ( strpos( $src, $base_url ) === 0 ) {
                        $rel = substr( $src, strlen( $base_url ) );
                        $local = $base_dir . $rel;
                        if ( ! file_exists( $local ) && preg_match( '/\.webp$/i', $local ) ) {
                            $orig = preg_replace( '/\.webp$/i', '', $local );
                            if ( file_exists( $orig ) ) {
                                $local = $orig;
                            }
                        }
                        if ( file_exists( $local ) ) {
                            $sz = @getimagesize( $local );
                            if ( $sz && ! empty( $sz[0] ) && ! empty( $sz[1] ) ) {
                                $w = (int) $sz[0];
                                $h = (int) $sz[1];
                                $dim_cache[ $src ] = array( 'w' => $w, 'h' => $h );
                            }
                        }
                    }
                }

                if ( $w && ! $has_w ) {
                    $tag = preg_replace( '/<img\b/i', '<img width="' . $w . '"', $tag );
                }
                if ( $h && ! $has_h ) {
                    $tag = preg_replace( '/<img\b/i', '<img height="' . $h . '"', $tag );
                }
            }

            // Alt attribute fallback
            if ( ! preg_match( '/\balt=["\'][^"\']+["\']/i', $tag ) ) {
                $alt = esc_attr( get_bloginfo( 'name' ) );
                if ( preg_match( '/BACP/i', $tag ) ) {
                    $alt = 'BACP Registered & Accredited Counsellor';
                } elseif ( preg_match( '/Attachment-Badge/i', $tag ) ) {
                    $alt = 'Attachment-Based Therapy Certified';
                } elseif ( preg_match( '/online-and-tel/i', $tag ) ) {
                    $alt = 'Online and Telephone Counselling UK';
                } elseif ( preg_match( '/Catherine-W/i', $tag ) ) {
                    $alt = 'Catherine Wallage - Trauma & Attachment Therapist';
                } elseif ( preg_match( '/Trauma/i', $tag ) ) {
                    $alt = 'Trauma Informed Therapy & Recovery';
                }

                if ( preg_match( '/\balt=["\']\s*["\']/i', $tag ) ) {
                    $tag = preg_replace( '/\balt=["\']\s*["\']/i', 'alt="' . $alt . '"', $tag );
                } else {
                    $tag = preg_replace( '/<img\b/i', '<img alt="' . $alt . '"', $tag );
                }
            }

            return $tag;
        }, $html );
    }

    /**
     * Unloads scripts and styles from sync plugins and unused features on the front page.
     */
    public function unload_sync_bloat() {
        if ( is_admin() ) return;
        
        wp_dequeue_script( 'lr-wp-bridge-js' );
        wp_dequeue_style( 'lr-wp-bridge-css' );

        // Dequeue dashicons and editor styles for non-logged-in frontend users
        if ( ! is_user_logged_in() ) {
            wp_dequeue_style( 'dashicons' );
            wp_deregister_style( 'dashicons' );

            $editor_styles = array(
                'wp-block-editor',
                'wp-editor',
                'wp-components',
                'wp-preferences',
                'wp-reusable-blocks',
                'wp-patterns',
                'wp-media-utils',
            );
            foreach ( $editor_styles as $handle ) {
                wp_dequeue_style( $handle );
                wp_deregister_style( $handle );
            }
        }

        // Dequeue WooCommerce styles if WooCommerce is inactive
        if ( ! class_exists( 'WooCommerce' ) ) {
            wp_dequeue_style( 'bard-woocommerce' );
            wp_deregister_style( 'bard-woocommerce' );
            wp_dequeue_style( 'woocommerce-general' );
            wp_dequeue_style( 'woocommerce-layout' );
            wp_dequeue_style( 'woocommerce-smallscreen' );
        }

        // Dequeue unused frontend TaxoPress styling
        wp_dequeue_style( 'taxopress-frontend-css' );
        wp_deregister_style( 'taxopress-frontend-css' );

        // Dequeue unused Bookly / WooCommerce assets on homepage if present
        if ( is_front_page() ) {
            $unused_frontpage_styles = array(
                'bookly-backend-globals',
                'bookly-frontend-globals',
                'bookly-tailwind.css',
                'bookly-ladda.min.css',
                'bookly-frontend-reset.css',
                'bookly-modern-booking-form-calendar.css',
                'bookly-bootstrap-icons.min.css',
                'bookly-intlTelInput.css',
                'bookly-bookly-main.css',
                'bookly-bootstrap.min.css',
                'bookly-customer-profile.css',
                'wc-blocks-style',
            );
            foreach ( $unused_frontpage_styles as $handle ) {
                wp_dequeue_style( $handle );
                wp_deregister_style( $handle );
            }

            $unused_frontpage_scripts = array(
                'bookly-spin.min.js',
                'bookly-globals',
                'bookly-ladda.min.js',
                'bookly-moment.min.js',
                'bookly-hammer.min.js',
                'bookly-jquery.hammer.min.js',
                'bookly-qrcode.js',
                'bookly-bookly.min.js',
                'bookly-intlTelInput.min.js',
                'bookly-bookly-core.js',
                'bookly-ai-assistant.js',
                'bookly-customer-profile.js',
                'wc-cart-fragments',
                'woocommerce',
                'jquery-blockui',
                'wc-jquery-blockui',
                'wc-add-to-cart',
                'sourcebuster-js',
                'wc-order-attribution',
                'woocommerce-analytics-client',
            );
            // Dequeue jQuery on homepage only for Gary's bespoke themes (pure vanilla JS), never on third-party themes
            $options = get_option( 'gw_perf_settings' );
            if ( get_template() === 'gary-wedding-pro' || ! empty( $options['dequeue_homepage_jquery'] ) ) {
                $unused_frontpage_scripts[] = 'jquery';
                $unused_frontpage_scripts[] = 'jquery-core';
                $unused_frontpage_scripts[] = 'jquery-migrate';
            }
            foreach ( $unused_frontpage_scripts as $handle ) {
                wp_dequeue_script( $handle );
                wp_deregister_script( $handle );
            }
        }
    }

    /**
     * Enforces font-display: swap on Google Fonts stylesheets.
     */
    public function add_font_display_swap( $src, $handle ) {
        if ( strpos( $src, 'fonts.googleapis.com/css' ) !== false && strpos( $src, 'display=' ) === false ) {
            $src = add_query_arg( 'display', 'swap', $src );
        }
        return $src;
    }

    /**
     * Injects preconnect hints for Google Fonts origins.
     */
    public function add_font_resource_hints( $urls, $relation_type ) {
        if ( 'preconnect' === $relation_type ) {
            $urls[] = array(
                'href' => 'https://fonts.googleapis.com',
                'crossorigin' => 'anonymous',
            );
            $urls[] = array(
                'href' => 'https://fonts.gstatic.com',
                'crossorigin' => 'anonymous',
            );
        }
        return $urls;
    }

    /**
     * --- INDUSTRIAL SETTINGS PAGE ---
     */
    public function add_settings_menu() {
        add_options_page( 'GW Performance', 'GW Performance', 'manage_options', 'gw-perf-settings', array( $this, 'render_settings_page' ) );
    }

    public function register_settings() {
        register_setting( 'gw_perf_settings_group', 'gw_perf_settings', array( $this, 'sanitize_perf_settings' ) );
    }

    public function sanitize_perf_settings( $input ) {
        $output = array();
        $output['ga4_id'] = isset( $input['ga4_id'] ) ? sanitize_text_field( $input['ga4_id'] ) : '';
        $output['gsc_id'] = isset( $input['gsc_id'] ) ? sanitize_text_field( $input['gsc_id'] ) : '';
        $output['business_name'] = isset( $input['business_name'] ) ? sanitize_text_field( $input['business_name'] ) : '';
        $output['schema_type'] = isset( $input['schema_type'] ) ? sanitize_text_field( $input['schema_type'] ) : 'LocalBusiness';
        $output['telephone'] = isset( $input['telephone'] ) ? sanitize_text_field( $input['telephone'] ) : '';
        $output['street_address'] = isset( $input['street_address'] ) ? sanitize_text_field( $input['street_address'] ) : '';
        $output['locality'] = isset( $input['locality'] ) ? sanitize_text_field( $input['locality'] ) : '';
        $output['postal_code'] = isset( $input['postal_code'] ) ? sanitize_text_field( $input['postal_code'] ) : '';
        $output['country'] = isset( $input['country'] ) ? sanitize_text_field( $input['country'] ) : 'GB';
        $output['default_og_image'] = isset( $input['default_og_image'] ) ? esc_url_raw( $input['default_og_image'] ) : '';
        if ( ! empty( $input['social_profiles_raw'] ) ) {
            $lines = explode( "\n", str_replace( "\r", '', $input['social_profiles_raw'] ) );
            $clean_lines = array();
            foreach ( $lines as $line ) {
                $trimmed = trim( $line );
                if ( $trimmed ) {
                    $clean_lines[] = esc_url_raw( $trimmed );
                }
            }
            $output['social_profiles'] = $clean_lines;
            $output['social_profiles_raw'] = implode( "\n", $clean_lines );
        } else {
            $output['social_profiles'] = array();
            $output['social_profiles_raw'] = '';
        }
        return $output;
    }

    public function render_settings_page() {
        $options = get_option( 'gw_perf_settings', array() );
        global $wpdb;
        $aioseo_table = $wpdb->prefix . 'aioseo_posts';
        $has_aioseo_table = (bool) $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $aioseo_table ) );
        $aioseo_post_count = $has_aioseo_table ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$aioseo_table} WHERE (description IS NOT NULL AND description != '') OR (title IS NOT NULL AND title != '')" ) : 0;
        ?>
        <div class="wrap">
            <h1>GW Industrial Performance & Bespoke SEO Settings</h1>

            <?php if ( isset( $_GET['aioseo_transferred'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Success:</strong> Successfully transferred <strong><?php echo (int) $_GET['aioseo_transferred']; ?></strong> posts/pages and global metadata from All In One SEO to GW Bespoke SEO!</p>
                </div>
            <?php endif; ?>

            <div class="notice notice-info" style="border-left-color: #C5A059;">
                <p><strong>GW Industrial Performance & SEO Engine:</strong> Replaces bloated SEO plugins (AIOSEO, Yoast) and tracking plugins (Site Kit, Jetpack Boost) with zero-overhead, ultra-fast code.</p>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( 'gw_perf_settings_group' ); ?>
                
                <h2>1. Lightweight Analytics & Webmaster Tools</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">GA4 Measurement ID</th>
                        <td>
                            <input type="text" name="gw_perf_settings[ga4_id]" value="<?php echo esc_attr( $options['ga4_id'] ?? '' ); ?>" placeholder="G-XXXXXXXXXX" class="regular-text" />
                            <p class="description">Your Google Analytics 4 Measurement ID.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Search Console ID</th>
                        <td>
                            <input type="text" name="gw_perf_settings[gsc_id]" value="<?php echo esc_attr( $options['gsc_id'] ?? '' ); ?>" placeholder="Verification Content String" class="regular-text" />
                            <p class="description">The 'content' value from your Google Search Console HTML verification tag.</p>
                        </td>
                    </tr>
                </table>

                <h2>2. Bespoke Business Schema (JSON-LD) & Social Sharing</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Business / Organization Name</th>
                        <td>
                            <input type="text" name="gw_perf_settings[business_name]" value="<?php echo esc_attr( $options['business_name'] ?? get_bloginfo( 'name' ) ); ?>" class="regular-text" />
                            <p class="description">Defaults to site title if blank.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Schema Type</th>
                        <td>
                            <?php $current_type = $options['schema_type'] ?? 'LocalBusiness'; ?>
                            <select name="gw_perf_settings[schema_type]">
                                <option value="LocalBusiness" <?php selected( $current_type, 'LocalBusiness' ); ?>>LocalBusiness</option>
                                <option value="ProfessionalService" <?php selected( $current_type, 'ProfessionalService' ); ?>>ProfessionalService</option>
                                <option value="Organization" <?php selected( $current_type, 'Organization' ); ?>>Organization</option>
                                <option value="HealthAndBeautyBusiness" <?php selected( $current_type, 'HealthAndBeautyBusiness' ); ?>>HealthAndBeautyBusiness</option>
                                <option value="Photographer" <?php selected( $current_type, 'Photographer' ); ?>>Photographer</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Telephone</th>
                        <td>
                            <input type="text" name="gw_perf_settings[telephone]" value="<?php echo esc_attr( $options['telephone'] ?? '' ); ?>" placeholder="+44 7..." class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Street Address</th>
                        <td>
                            <input type="text" name="gw_perf_settings[street_address]" value="<?php echo esc_attr( $options['street_address'] ?? '' ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Locality (City / Town)</th>
                        <td>
                            <input type="text" name="gw_perf_settings[locality]" value="<?php echo esc_attr( $options['locality'] ?? '' ); ?>" placeholder="Swindon" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Postal Code</th>
                        <td>
                            <input type="text" name="gw_perf_settings[postal_code]" value="<?php echo esc_attr( $options['postal_code'] ?? '' ); ?>" placeholder="SN25..." class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Country Code</th>
                        <td>
                            <input type="text" name="gw_perf_settings[country]" value="<?php echo esc_attr( $options['country'] ?? 'GB' ); ?>" placeholder="GB" style="width: 80px;" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Social Share Image URL</th>
                        <td>
                            <input type="text" name="gw_perf_settings[default_og_image]" value="<?php echo esc_attr( $options['default_og_image'] ?? '' ); ?>" placeholder="https://.../og-image.jpg" class="regular-text" />
                            <p class="description">Fallback OpenGraph / Twitter card image when a page lacks a featured image.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Social Profile URLs (sameAs)</th>
                        <td>
                            <textarea name="gw_perf_settings[social_profiles_raw]" rows="4" cols="50" class="large-text"><?php echo esc_textarea( $options['social_profiles_raw'] ?? ( ! empty( $options['social_profiles'] ) ? implode( "\n", $options['social_profiles'] ) : '' ) ); ?></textarea>
                            <p class="description">Enter one profile URL per line (Facebook, Instagram, LinkedIn, Twitter/X, etc.).</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Save Performance & SEO Settings' ); ?>
            </form>

            <?php if ( $has_aioseo_table && $aioseo_post_count > 0 ) : ?>
                <hr style="margin-top: 30px;" />
                <h2>3. SEO Data Migration Tool</h2>
                <div class="card" style="max-width: 800px; padding: 15px 20px; border-left: 4px solid #2271b1;">
                    <h3>Transfer Data from All in One SEO to GW Bespoke SEO</h3>
                    <p>Detected <strong><?php echo $aioseo_post_count; ?></strong> custom SEO entries in the All In One SEO table (<code><?php echo esc_html( $aioseo_table ); ?></code>).</p>
                    <p>Clicking the button below will migrate all custom SEO titles, meta descriptions, canonical URLs, and noindex flags into WordPress post metadata (<code>_gw_seo_*</code>) and import your GSC ID, social links, and Organization name into GW settings above. Once transferred, you can safely deactivate All In One SEO.</p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'gw_transfer_aioseo_action' ); ?>
                        <input type="hidden" name="action" value="gw_transfer_aioseo" />
                        <?php submit_button( 'Transfer All SEO Data to GW Performance', 'secondary', 'submit', false ); ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Admin POST handler for AIOSEO migration
     */
    public function handle_transfer_aioseo() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'gw_transfer_aioseo_action' );
        $count = self::transfer_aioseo_data();
        wp_safe_redirect( add_query_arg( array( 'page' => 'gw-perf-settings', 'aioseo_transferred' => $count ), admin_url( 'options-general.php' ) ) );
        exit;
    }

    /**
     * One-Click SEO Data Migration from All in One SEO to GW Bespoke SEO
     */
    public static function transfer_aioseo_data() {
        global $wpdb;
        $table = $wpdb->prefix . 'aioseo_posts';
        $count = 0;

        // 1. Transfer global options if present
        $aioseo_options = get_option( 'aioseo_options' );
        if ( ! empty( $aioseo_options ) && is_string( $aioseo_options ) ) {
            $opt = json_decode( $aioseo_options, true );
            $gw_opts = get_option( 'gw_perf_settings', array() );
            if ( ! is_array( $gw_opts ) ) $gw_opts = array();

            if ( ! empty( $opt['webmasterTools']['google'] ) && empty( $gw_opts['gsc_id'] ) ) {
                $gw_opts['gsc_id'] = sanitize_text_field( $opt['webmasterTools']['google'] );
            }
            if ( ! empty( $opt['searchAppearance']['global']['schema']['organizationName'] ) && empty( $gw_opts['business_name'] ) ) {
                $gw_opts['business_name'] = sanitize_text_field( $opt['searchAppearance']['global']['schema']['organizationName'] );
            }
            if ( ! empty( $opt['social']['urls'] ) ) {
                $urls = array_filter( array_values( $opt['social']['urls'] ) );
                $gw_opts['social_profiles'] = array_map( 'esc_url_raw', $urls );
                $gw_opts['social_profiles_raw'] = implode( "\n", $gw_opts['social_profiles'] );
            }
            if ( ! empty( $opt['social']['facebook']['general']['defaultImagePosts'] ) && empty( $gw_opts['default_og_image'] ) ) {
                $gw_opts['default_og_image'] = esc_url_raw( $opt['social']['facebook']['general']['defaultImagePosts'] );
            }
            update_option( 'gw_perf_settings', $gw_opts );
        }

        // 2. Check table exists
        $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
        if ( ! $table_exists ) {
            return 0;
        }

        $rows = $wpdb->get_results( "SELECT post_id, title, description, canonical_url, robots_noindex FROM {$table} WHERE (description IS NOT NULL AND description != '') OR (title IS NOT NULL AND title != '')" );
        if ( empty( $rows ) ) {
            return 0;
        }

        foreach ( $rows as $r ) {
            $post_id = (int) $r->post_id;
            if ( ! $post_id ) continue;

            $updated_post = false;

            // Description
            if ( ! empty( $r->description ) && strpos( $r->description, '#post_content' ) === false ) {
                $clean_desc = trim( html_entity_decode( (string) $r->description, ENT_QUOTES, 'UTF-8' ) );
                if ( $clean_desc ) {
                    update_post_meta( $post_id, '_gw_seo_description', $clean_desc );
                    $updated_post = true;
                }
            }

            // Title
            if ( ! empty( $r->title ) && $r->title !== '#post_title' && $r->title !== '#post_title #separator_sa #site_title' ) {
                $clean_title = str_replace( array( '#separator_sa', '#site_title', '#post_title' ), array( '|', get_bloginfo( 'name' ), get_the_title( $post_id ) ), (string) $r->title );
                $clean_title = trim( preg_replace( '/\s+/', ' ', $clean_title ) );
                if ( $clean_title ) {
                    update_post_meta( $post_id, '_gw_seo_title', $clean_title );
                    $updated_post = true;
                }
            }

            // Canonical
            if ( ! empty( $r->canonical_url ) ) {
                update_post_meta( $post_id, '_gw_seo_canonical', esc_url_raw( $r->canonical_url ) );
                $updated_post = true;
            }

            // Robots Noindex
            if ( ! empty( $r->robots_noindex ) ) {
                update_post_meta( $post_id, '_gw_seo_noindex', '1' );
                $updated_post = true;
            }

            if ( $updated_post ) {
                $count++;
            }
        }

        return $count;
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

        if ( is_front_page() ) {
            // Check custom header image first (e.g. Bard, Astra, GeneratePress hero banners)
            $header_img = get_header_image();
            if ( $header_img ) {
                $preload_url = $header_img;
            } else {
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
        }

        if ( ! $preload_url && is_singular() && has_post_thumbnail() ) {
            $thumb_id = get_post_thumbnail_id();
            $src = wp_get_attachment_image_src( $thumb_id, 'large' );
            if ( $src && ! empty( $src[0] ) ) {
                $preload_url = $src[0];
            }
        }

        if ( $preload_url ) {
            // Swap to WebP sibling if present on disk
            $uploads = wp_upload_dir();
            $base_url = $uploads['baseurl'];
            $base_dir = $uploads['basedir'];
            if ( strpos( $preload_url, $base_url ) === 0 ) {
                $rel = ltrim( substr( $preload_url, strlen( $base_url ) ), '/' );
                $base_rel = preg_replace( '/\.(?:png|jpe?g)$/i', '', $rel );
                if ( file_exists( $base_dir . '/' . $base_rel . '.webp' ) ) {
                    $preload_url = $base_url . '/' . $base_rel . '.webp';
                } elseif ( file_exists( $base_dir . '/' . $rel . '.webp' ) ) {
                    $preload_url = $base_url . '/' . $rel . '.webp';
                }
            }

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

    /**
     * Enhancement 14a: Computes the dominant color of the full-size image and
     * whether it has transparency, storing both on the attachment metadata.
     * Replaces the dominant-color-images plugin: same 1x1-downscale trick
     * (GD's resampling averages every pixel into one), same metadata keys
     * ('dominant_color', 'has_transparency') so any existing front-end code
     * keyed on those keeps working unchanged.
     */
    public function compute_dominant_color_metadata( $metadata, $attachment_id ) {
        if ( ! is_array( $metadata ) ) $metadata = array();

        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! file_exists( $file ) ) return $metadata;

        $color_data = $this->extract_dominant_color( $file, get_post_mime_type( $attachment_id ) );
        if ( $color_data ) {
            $metadata['dominant_color']   = $color_data['color'];
            $metadata['has_transparency'] = $color_data['has_transparency'];
        }

        return $metadata;
    }

    /**
     * Loads the image with GD, downsamples it to 1x1px to get the average
     * (dominant) color, and grid-samples for transparency instead of scanning
     * every pixel (real photography never has alpha, so this stays cheap for
     * the common case while still catching transparent PNG/WebP/AVIF assets).
     */
    private function extract_dominant_color( $file, $mime ) {
        switch ( $mime ) {
            case 'image/jpeg': $src = @imagecreatefromjpeg( $file ); break;
            case 'image/png':  $src = @imagecreatefrompng( $file ); break;
            case 'image/gif':  $src = @imagecreatefromgif( $file ); break;
            case 'image/webp': $src = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $file ) : false; break;
            case 'image/avif': $src = function_exists( 'imagecreatefromavif' ) ? @imagecreatefromavif( $file ) : false; break;
            default: return null;
        }
        if ( ! $src ) return null;

        imagepalettetotruecolor( $src );

        $w = imagesx( $src );
        $h = imagesy( $src );
        if ( $w < 1 || $h < 1 ) { imagedestroy( $src ); return null; }

        $tiny = imagecreatetruecolor( 1, 1 );
        imagecopyresampled( $tiny, $src, 0, 0, 0, 0, 1, 1, $w, $h );
        $rgb = imagecolorat( $tiny, 0, 0 );
        imagedestroy( $tiny );

        $r = ( $rgb >> 16 ) & 0xFF;
        $g = ( $rgb >> 8 ) & 0xFF;
        $b = $rgb & 0xFF;

        $has_transparency = false;
        if ( in_array( $mime, array( 'image/png', 'image/gif', 'image/webp', 'image/avif' ), true ) ) {
            $step_x = max( 1, (int) floor( $w / 50 ) );
            $step_y = max( 1, (int) floor( $h / 50 ) );
            for ( $x = 0; $x < $w && ! $has_transparency; $x += $step_x ) {
                for ( $y = 0; $y < $h; $y += $step_y ) {
                    $px = imagecolorat( $src, $x, $y );
                    if ( ( ( $px >> 24 ) & 0x7F ) > 0 ) { $has_transparency = true; break; }
                }
            }
        }

        imagedestroy( $src );

        return array(
            'color'            => sprintf( '%02x%02x%02x', $r, $g, $b ),
            'has_transparency' => $has_transparency,
        );
    }

    /**
     * Enhancement 14b: Applies the stored dominant color as a CSS custom
     * property + data attribute. Same markup contract as the
     * dominant-color-images plugin ([data-dominant-color], --dominant-color)
     * so it's a drop-in replacement for any CSS already keyed on it.
     */
    public function apply_dominant_color_attributes( $attr, $attachment ) {
        $meta = wp_get_attachment_metadata( $attachment->ID );
        if ( ! is_array( $meta ) || empty( $meta['dominant_color'] ) ) return $attr;

        $attr['data-dominant-color'] = esc_attr( $meta['dominant_color'] );
        $style          = isset( $attr['style'] ) ? $attr['style'] : '';
        $attr['style']  = '--dominant-color: #' . esc_attr( $meta['dominant_color'] ) . ';' . $style;

        if ( ! empty( $meta['has_transparency'] ) ) {
            $attr['data-has-transparency'] = 'true';
        }

        return $attr;
    }

    /**
     * Enhancement 14c: Background-color CSS driven by the --dominant-color
     * custom property, applied only to non-transparent images.
     */
    public function inject_dominant_color_style() {
        wp_register_style( 'gw-dominant-color', false );
        wp_enqueue_style( 'gw-dominant-color' );
        wp_add_inline_style( 'gw-dominant-color', 'img[data-dominant-color]:not([data-has-transparency="true"]) { background-color: var(--dominant-color); }' );
    }
}

new GW_Performance_Engine();
