package AssetThumbnailMagick::Plugin;
use strict;
use File::Spec;

sub _hdlr_get_thumbnail {
    my ( $ctx, $args ) = @_;
    my $asset = $ctx->stash( 'asset' )
        or return $ctx->_no_asset_error();
    return '' unless $asset->has_thumbnail;
    my $type = lc( $args->{ type } );
    my %arg;
    foreach ( keys %$args ) {
        $arg{ $_ } = $args->{ $_ };
    }
    $type = 'circle' unless $type;
    my $add_str = '-' . $type;
    if ( my $composite = $args->{ composite } ) {
        $add_str .= '-' . $composite;
    }
    $arg{ Width }  = $args->{ width }  if $args->{ width };
    $arg{ Height } = $args->{ height } if $args->{ height };
    $arg{ Scale }  = $args->{ scale }  if $args->{ scale };
    $arg{ Square } = $args->{ square } if $args->{ square };
    my ( $file, $w, $h ) = $asset->thumbnail_file( %arg );
    my $suffix = ( split( /\./, $file ) )[-1];
    $suffix = lc( $suffix );
    my $params = { file => $file,
                   suffix => $suffix,
                   add_str => $add_str,
                   type => $type,
                   round => $args->{ round },
                   force => $args->{ force },
                   sp_icon => $args->{ sp_icon },
                   opacity => $args->{ opacity },
                   composite => $args->{ composite } };
    $file = __mask( $params );
    my ( $url, $w, $h ) = $asset->thumbnail_url( %arg );
    $url =~ s/\.$suffix$/$add_str.png/i;
    if ( $args->{ wants } && $args->{ wants } eq 'path' ) {
        return $file;
    }
    return $url || '';
}

sub __mask {
    my $params = shift;
    my $plugin = MT->component( 'AssetThumbnailMagick' );
    my $photo = $params->{ file };
    my $suffix = $params->{ suffix };
    my $add_str = $params->{ add_str };
    my $type = $params->{ type };
    my $round = $params->{ round };
    $round = 30 unless $round;
    my $force = $params->{ force };
    my $sp_icon = $params->{ sp_icon };
    my $opacity = $params->{ opacity };
    $opacity = 0.5 unless $opacity;
    $opacity = int( $opacity * 100 );
    $opacity .= '%';
    my $composite = $params->{ composite };
    require MT::FileMgr;
    my $fmgr = MT::FileMgr->new( 'Local' ) or die MT::FileMgr->errstr;
    if ( $fmgr->exists( $photo ) ) {
        require Image::Magick;
        my $image = Image::Magick->new();
        $image->Read( $photo );
        my $new = $photo;
        $new =~ s/\.$suffix$/$add_str.png/i;
        if (! $force ) {
            if ( $fmgr->exists( $new ) ) {
                if ( $fmgr->file_mod_time( $new ) > $fmgr->file_mod_time( $photo ) ) {
                    return $new;
                }
            }
        }
        my $size;
        my ( $width, $height ) = $image->Get( 'width', 'height' );
        my $mask = Image::Magick->new();
        $mask->Set( size => "${width}x${height}" );
        $mask->ReadImage( 'xc:none' );
        my $image_mask;
        if ( $composite ) {
            my $base = File::Spec->catfile( $plugin->path, 'composites', $composite . '.png' );
            if ( $fmgr->exists( $base ) ) {
                my $composite_img = Image::Magick->new;
                $composite_img->Read( $base );
                my ( $base_width, $base_height ) = $composite_img->Get( 'width', 'height' );
                $image->Resize( width => $base_width, height => $base_height );
                $image_mask = 1;
                $image->Composite( image => $composite_img,
                                   compose=> 'Dissolve',
                                   'x' => 0 ,
                                   'y' => 0,
                                   opacity => $opacity,
                                   antialias => 'true' );
                if ( $round ) {
                    $round = int ( $base_width * ( $round / $width ) );
                    $mask->Resize( width => $base_width, height => $base_height );
                    $mask->Draw( primitive => 'roundRectangle',
                                 antialias => 1,
                                 fill => '#FFFFFF',
                                 points => "0,0,${base_width},${base_height},${round},${round}" );
                }
                undef $composite_img;
            }
        }
        if ( $type eq 'circle' ) {
            my $harf_w = $width / 2;
            my $harf_h = $height / 2;
            my $harf_w2 = $harf_w - 1;
            my $harf_h2 = $harf_h - 1;
            $mask->Draw( primitive => 'ellipse',
                         antialias => 'true',
                         fill => '#FFFFFF',
                         points => "${harf_w},${harf_h},${harf_w2},${harf_h2},0,360" );
        } elsif ( $type eq 'roundrectangle' ) {
            $mask->Draw( primitive => 'roundRectangle',
                         antialias => 1,
                         fill => '#FFFFFF',
                         points => "0,0,${width},${height},${round},${round}" );
        } else {
            my $base = File::Spec->catfile( $plugin->path, 'masks', $type . '.png' );
            if ( $fmgr->exists( $base ) ) {
                $mask = Image::Magick->new;
                $mask->Read( $base );
                $mask->Resize( width => $width * 2, height => $height * 2 );
                $image->Resize( width => $width * 2, height => $height * 2 );
                $image_mask = 1;
            }
        }
        $mask->Composite( 'image' => $image, 'mask' => $mask );
        if ( $image_mask ) {
            $mask->Resize( width => $width, height => $height );
        }
        binmode( STDOUT );
        $mask->Write( "png:${new}" );
        undef $mask;
        undef $image;
        return $new;
    }
    return $photo;
}

1;