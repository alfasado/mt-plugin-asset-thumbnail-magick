<?php
function smarty_function_mtassetthumbnailmagick ( $args, &$ctx ) {
    $app = $ctx->stash( 'bootstrapper' );
    $asset = $ctx->stash( 'asset' );
    if (! $asset ) return '';
    if ( $asset->asset_class != 'image' ) return '';
    $blog = $ctx->stash( 'blog' );
    if (! $blog ) return '';
    $type = strtolower( $args[ 'type' ] );
    if (! $type ) $type = 'circle';
    $add_str = '-' . $type;
    $round = $args[ 'round' ];
    require_once( 'MTUtil.php' );
    list( $url, $width, $height, $file ) = get_thumbnail_file( $asset, $blog, $args );
    $pinfo = pathinfo( $file );
    $suffix = $pinfo[ 'extension' ];
    $new = preg_replace( "/\.$suffix$/", $add_str . ".png", $file );
    $url = preg_replace( "/\.$suffix$/", $add_str . ".png", $url );
    if (! $args[ 'force' ] ) {
        if ( file_exists( $new ) ) {
            if ( filemtime( $new ) > filemtime( $file ) ) {
                if ( $args[ 'wants' ] && ( $args[ 'wants' ] === 'path' ) ) {
                    return $new;
                }
                return $url;
            }
        }
    }
    $im = new Imagick( $file );
    $mask;
    $image_mask;
    if ( ( $type === 'circle' ) || ( $type === 'roundrectangle' ) ) {
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
    } else {
        $plugin_dir;
        $pathes = $app->config( 'pluginpath' );
        foreach ( $pathes as $path ) {
            if (! preg_match('/addons$/', $path ) ) {
                $plugin_dir = $path;
                break;
            }
        }
        $base = $plugin_dir . DIRECTORY_SEPARATOR . 'AssetThumbnailMagick' .
                      DIRECTORY_SEPARATOR . 'masks' . DIRECTORY_SEPARATOR . $type . '.png';
        if ( file_exists( $base ) ) {
            $mask = new Imagick( $base );
            $mask->resizeImage( $width * 2, $height * 2, imagick::FILTER_MITCHELL, 1, FALSE );
            $im->resizeImage( $width*2, $height * 2, imagick::FILTER_MITCHELL, 1, FALSE );
            $image_mask = 1;
            // $mask->resizeImage( $width, $height, imagick::FILTER_MITCHELL, 1, FALSE );
        }
    }
    $mask->compositeImage( $im, Imagick::COMPOSITE_IN, 0, 0, Imagick::CHANNEL_ALL );
    if ( $image_mask ) {
        $mask->resizeImage( $width, $height, imagick::FILTER_MITCHELL, 1, FALSE );
    }
    $mask->writeImage( $new );
    $idraw->destroy();
    $mask->destroy();
    if ( $args[ 'wants' ] && ( $args[ 'wants' ] === 'path' ) ) {
        return $new;
    }
    return $url;
}
?>