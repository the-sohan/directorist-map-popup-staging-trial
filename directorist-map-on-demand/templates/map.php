<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="directorist-archive-items directorist-archive-map-view">
    <?php
    do_action( 'directorist_before_map_listings_loop' );
    BBQ7833_Map_Popups::render_map( $listings );
    if ( $listings->show_pagination ) {
        do_action( 'directorist_before_listings_pagination' );
        $listings->pagination();
        do_action( 'directorist_after_listings_pagination' );
    }
    do_action( 'directorist_after_map_listings_loop' );
    ?>
</div>
