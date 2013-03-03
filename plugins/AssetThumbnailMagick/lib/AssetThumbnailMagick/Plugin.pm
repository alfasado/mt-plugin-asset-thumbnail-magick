package AssetThumbnailMagick::Plugin;
use strict;

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
    my $add_str = '-circle';
    if ( $type =~ /rectangle$/ ) {
        $add_str = '-rectangle';
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
                   round => $args->{ round } };
    $file = __convert2thumbnail( $params );
    my ( $url, $w, $h ) = $asset->thumbnail_url( %arg );
    $url =~ s/(\.$suffix$)/$add_str.png/i;
    if ( $args->{ wants } && $args->{ wants } eq 'path' ) {
        return $file;
    }
    return $url || '';
}

sub __convert2thumbnail {
    my $params = shift;
    my $photo = $params->{ file };
    my $suffix = $params->{ suffix };
    my $add_str = $params->{ add_str };
    my $type = $params->{ type };
    my $round = $params->{ round };
    require MT::FileMgr;
    my $fmgr = MT::FileMgr->new( 'Local' ) or die MT::FileMgr->errstr;
    if ( $fmgr->exists( $photo ) ) {
        require Image::Magick;
        my $image = Image::Magick->new();
        $image->Read( $photo );
        my $new = $photo;
        $new =~ s/(\.$suffix$)/$add_str.png/i;
        if ( $fmgr->exists( $new ) ) {
            if ( $fmgr->file_mod_time( $new ) > $fmgr->file_mod_time( $photo ) ) {
                return $new;
            }
        }
        my $size;
        my ( $width, $height ) = $image->Get( 'width', 'height' );
        my $harf_w = $width / 2;
        my $harf_h = $height / 2;
        my $mask = Image::Magick->new;
        $mask->Set( size=>"${width}x${height}" );
        $mask->ReadImage( 'xc:none' );
        if ( $type eq 'circle' ) {
            $mask->Draw( primitive => 'ellipse',
                         fill=>'#FFFFFF',
                         points=>"${harf_w},${harf_h},${harf_w},${harf_h},0,360" );
        } else {
            $mask->Draw( primitive => 'roundRectangle',
                         fill=>'#FFFFFF',
                         points=>"0,0,${width},${height},${round},${round}" );
        }
        $mask->Composite( 'image' => $image, 'mask' => $mask );
        binmode( STDOUT );
        $mask->Write( "png:${new}" );
        undef $mask;
        undef $image;
        return $new;
    }
    return $photo;
}

1;