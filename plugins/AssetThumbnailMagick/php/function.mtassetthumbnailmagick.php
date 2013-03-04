<?php
function smarty_function_mtassetthumbnailmagick ( $args, &$ctx ) {
    $asset = $ctx->stash( 'asset' );
    if (! $asset ) return '';
    if ( $asset->asset_class != 'image' ) return '';
    $blog = $ctx->stash( 'blog' );
    if (! $blog ) return '';
    $type = strtolower( $args[ 'type' ] );
    if (! $type ) $type = 'circle';
    $add_str = '-circle';
    if ( preg_match( "/rectangle$/", $type ) ) {
        $add_str = '-rectangle';
    }
    $round =  $args[ 'round' ];
    require_once( 'MTUtil.php' );
    list( $url, $width, $height, $file ) = get_thumbnail_file( $asset, $blog, $args );
    $pinfo = pathinfo( $file );
    $suffix = $pinfo[ 'extension' ];
    $new = preg_replace( "/\.$suffix$/", $add_str . ".png", $file );
    $url = preg_replace( "/\.$suffix$/", $add_str . ".png", $url );
    $im = new Imagick( $file );
    $mask = new Imagick();
    $mask->newImage( $width, $height, 'none', 'png' );
    $idraw = new ImagickDraw();
    $idraw->setFillColor( "#FFFFFF" );
    if ( $type === 'circle' ) {
        $idraw->ellipse( $width/2, $height/2, $width/2-1, $height/2-1, 0, 360 );
    } else {
        $idraw->roundRectangle( 0, 0, $width, $height, $round, $round );
    }
    $mask->drawImage( $idraw );
    $mask->compositeImage( $im, Imagick::COMPOSITE_IN, 0, 0, Imagick::CHANNEL_ALL );
    $mask->writeImage( $new );
    $idraw->destroy();
    $mask->destroy();
    if ( $args[ 'wants' ] && ( $args[ 'wants' ] === 'path' ) ) {
        return $new;
    }
    return $url;
}
?>