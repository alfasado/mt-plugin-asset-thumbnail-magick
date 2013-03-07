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
    if ( $composite = $args[ 'composite' ] ) {
        $add_str .= '-' . $composite;
    }
    $round = $args[ 'round' ];
    if (! $round ) $round = 30;
    $opacity = $args[ 'opacity' ];
    if (! $opacity ) $opacity = 0.5;
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
    $plugin_dir;
    $pathes = $app->config( 'pluginpath' );
    foreach ( $pathes as $path ) {
        if (! preg_match('/addons$/', $path ) ) {
            $plugin_dir = $path;
            break;
        }
    }
    $im = new Imagick( $file );
    $idraw = new ImagickDraw();
    $mask;
    $image_mask;
    if ( $composite ) {
        $base = $plugin_dir . DIRECTORY_SEPARATOR . 'AssetThumbnailMagick' .
                      DIRECTORY_SEPARATOR . 'composites' . DIRECTORY_SEPARATOR . $composite . '.png';
        if ( file_exists( $base ) ) {
            $composite_img = new Imagick( $base );
            $composite_img->setImageOpacity( $opacity );
            $base_width = $composite_img->getImageWidth;
            $base_height = $composite_img->getImageHeight;
            $im->resizeImage( $base_width, $base_height, imagick::FILTER_MITCHELL, 1, FALSE );
            $im->compositeImage( $composite_img, Imagick::COMPOSITE_DISSOLVE, 0, 0 );
            $image_mask = 1;
            if ( $round ) {
                $round = round ( $base_width * ( $round / $width ) );
                $idraw->roundRectangle( 0, 0, $base_width, $base_height, $round, $round );
            }
        }
    }
    if ( ( $type === 'circle' ) || ( $type === 'roundrectangle' ) ) {
        $mask = new Imagick();
        $mask->newImage( $width, $height, 'none', 'png' );
        $idraw->setFillColor( "#FFFFFF" );
        if ( $type === 'circle' ) {
            $idraw->ellipse( $width/2, $height/2, $width/2-1, $height/2-1, 0, 360 );
        } else {
            $round = $args[ 'round' ];
            $idraw->roundRectangle( 0, 0, $width, $height, $round, $round );
        }
        $mask->drawImage( $idraw );
    } else {
        $base = $plugin_dir . DIRECTORY_SEPARATOR . 'AssetThumbnailMagick' .
                      DIRECTORY_SEPARATOR . 'masks' . DIRECTORY_SEPARATOR . $type . '.png';
        if ( file_exists( $base ) ) {
            $mask = new Imagick( $base );
            $mask->resizeImage( $width * 2, $height * 2, imagick::FILTER_MITCHELL, 1, FALSE );
            $im->resizeImage( $width*2, $height * 2, imagick::FILTER_MITCHELL, 1, FALSE );
            $image_mask = 1;
        }
    }
    $mask->compositeImage( $im, Imagick::COMPOSITE_IN, 0, 0, Imagick::CHANNEL_ALL );
    if ( $image_mask ) {
        $mask->resizeImage( $width, $height, imagick::FILTER_MITCHELL, 1, FALSE );
    }
    $mask->writeImage( $new );
    $im->destroy();
    if ( isset $idraw ) $idraw->destroy();
    $mask->destroy();
    if ( $args[ 'wants' ] && ( $args[ 'wants' ] === 'path' ) ) {
        return $new;
    }
    return $url;
}
?>