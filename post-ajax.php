<?php
/**
 * Plugin Name:  Post Filter
 * Plugin URI:   #
 * Author:       Rawshan
 * Author URI:   http://addonmaster.com/plugins/post-grid-with-ajax-filter
 * Version: 	  3.0.3
 * Description:  Post Grid with Ajax Filter helps you filter your posts by category terms with Ajax. Infinite scroll function included.
 * License:      GPL2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  pf
 * Domain Path:  /lang
 */

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

// Defines
define('AM_POST_GRID_VERSION', '3.0.3');

/**
 * Loading Text Domain
 */
add_action('plugins_loaded', 'pf_grid_plugin_loaded_action', 10, 2);
function pf_grid_plugin_loaded_action() {
    load_plugin_textdomain( 'am_post_grid', false, dirname( plugin_basename(__FILE__) ) . '/lang/' );
}

/**
 * Assets Loaded
 */
function pf_assets() {

//    style load
    wp_enqueue_style('post-filter', plugin_dir_url( __FILE__ ) . 'assets/css/post-filter.css');
    wp_enqueue_script('post-filter', plugin_dir_url( __FILE__ ) . 'assets/js/post-filter.js', ['jquery'], AM_POST_GRID_VERSION, true);

    // Localization
    wp_localize_script( 'post-filter', 'pf_ajax_params', array(
            'pf_ajax_nonce' => wp_create_nonce( 'pf_ajax_nonce' ),
            'pf_ajax_url' => admin_url( 'admin-ajax.php' ),
        )
    );
}
add_action('wp_enqueue_scripts', 'pf_assets');


/**
 * Post Filter Shortcode
 */
function pf_shortcode_mapper($atts, $content = null) {

    $shortcode_atts = shortcode_atts(
        array(
            'show_filter' 		=> "yes",
            'btn_all' 			=> "yes",
            'initial' 			=> "-1",
            'layout' 			=> '1',
            'post_type' 		=> 'post',
            'posts_per_page' 	=> '2',
            'cat' 				=> '',
            'terms' 			=> '',
            'paginate' 			=> 'no',
            'hide_empty' 		=> 'true',
            'orderby' 			=> 'menu_order date', //Display posts sorted by ‘menu_order’ with a fallback to post ‘date’
            'order'   			=> 'DESC',
            'pagination_type'   => 'load_more',
            'infinite_scroll'   => '',
            'animation'  		=> '',
        ),
        $atts
    );

    // Params extraction
    extract($shortcode_atts);
    ob_start();

    $taxonomy = 'category';
    $args = array(
        'hide_empty' => $hide_empty,
        'taxonomy' => $taxonomy,
        'include' => $terms ? $terms : $cat,
    );
    $terms = get_terms($args);
    ?>
    <div class="pf-post-wrapper" data-pagination_type="<?php echo esc_attr($pagination_type);?>" data-pf_post_grid='<?php echo json_encode($shortcode_atts);?>'>

        <?php if ($show_filter ==  'yes' || $terms && !is_wp_error($terms) ): ?>
            <div class="pf-post-filter">
                <ul>
                    <li class="pf-filter active" data_id="-1"><?php echo esc_html__('All', 'pf') ?></li>
                    <?php foreach( $terms as $term ) { ?>
                        <li class="pf-filter" data_id="<?php echo $term->term_id; ?>"><?php echo $term->name; ?></li>
                    <?php } ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="pf-post-container">
            <div class="asr-loader">
                <div class="lds-dual-ring"></div>
            </div>


            <div class="pf-post-result"></div>
        </div>
    </div>
    <?php




    return ob_get_clean();

}
add_shortcode('post_filter', 'pf_shortcode_mapper');

/**
 * Load ajax
 */
add_action('wp_ajax_pf_post_filter', 'pf_post_filter_functions');
add_action('wp_ajax_nopriv_pf_post_filter', 'pf_post_filter_functions');

function pf_post_filter_functions() {
    /**
     * nonce varify
     */
    if( !isset( $_POST['pf_ajax_nonce'] ) || !wp_verify_nonce( $_POST['pf_ajax_nonce'], 'pf_ajax_nonce' ) )
        die('Permission denied');

    /**
     * Term ID
     */
    $term_ID = isset( $_POST['term_ID'] ) ? sanitize_text_field( intval($_POST['term_ID']) ) : null;

    /**
     * Pagination
     */
    $pagination_type = isset( $_POST['pagination_type'] ) ? sanitize_text_field( $_POST['pagination_type'] ) : null;

    if( $_POST['paged'] ) {
        $dataPaged = intval($_POST['paged']);
    } else {
        $dataPaged = get_query_var('paged') ? get_query_var('paged') : 1;
    }

    /**
     * Json Data
     */
    $jsonData = json_decode( str_replace('\\', '', $_POST['jsonData']), true );

    /**
     * Add infinite_scroll to button
     */
    $infinite_scroll_class = isset( $jsonData['infinite_scroll'] ) && $jsonData['infinite_scroll'] == "true" ? ' infinite_scroll ' : '';

    $data = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'paged' => $dataPaged,
    );

    /**
     * If json data found
     */
    if( $jsonData ){
        if( $jsonData['posts_per_page'] ){
            $data['posts_per_page'] = intval( $jsonData['posts_per_page'] );
        }

        if( $jsonData['orderby'] ){
            $data['orderby'] = sanitize_text_field( $jsonData['orderby'] );
        }

        if( $jsonData['order'] ){
            $data['order'] = sanitize_text_field( $jsonData['order'] );
        }
    }

    /**
     *  Bind to Category Terms
     */
    if( $term_ID == -1 ){
        if ( isset( $jsonData['cat'] ) && !empty( $jsonData['cat'] ) ) {
            $term_ID = explode(',', $jsonData['cat']);
        } elseif ( isset( $jsonData['terms'] ) && !empty( $jsonData['terms'] ) ) {
            $term_ID = explode(',', $jsonData['terms']);
        } else {
            $term_ID =  null;
        }
    }

    /**
     * Check if set terms
     */
    if( $term_ID ){
        $data['tax_query'] = array(
            array(
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => $term_ID,
            )
        );
    }

    $query = new WP_Query($data);

    ob_start();
    echo ( $pagination_type == 'load_more' ) ? '<div class="am-postgrid-wrapper">' : '';
    if ($query->have_posts()) {?>
        <div class="pf-post-layout">
            <?php while ($query->have_posts()): $query->the_post(); ?>
                <h1><?php echo get_the_title(); ?></h1>
            <?php endwhile; ?>
        </div>
        <div class="pf-post-pagination">
            <?php
            $big = 999999999; // need an unlikely integer
            $dataNext = $dataPaged+1;

            $paged = ( get_query_var( 'paged' ) ) ? absint( get_query_var( 'paged' ) ) : 1;

            $paginate_links = paginate_links( array(
                'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
                'format' => '?paged=%#%',
                'current' => max( 1, $dataPaged ),
                'mid_size' => 2,
                'total' => $query->max_num_pages,
                'base' => home_url( '/%_%' ),
                'prev_text'    => __('Prev'),
                'next_text'    => __('Next'),
            ) );

            if ($pagination_type === 'load_more') {
                if( $paginate_links && $dataPaged < $query->max_num_pages ){
                    echo "<button type='button' data-paged='{$dataPaged}' data-next='{$dataNext}' class='{$infinite_scroll_class} am-post-grid-load-more'>".esc_html__( 'Load More', 'am_post_grid' )."</button>";
                }
            } else {
                echo "<div id='am_posts_navigation_init'>{$paginate_links}</div>";
            }


            ?>
        </div>
    <?php }
    wp_reset_postdata();
    echo ob_get_clean();
    die();

}