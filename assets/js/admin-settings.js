jQuery( document ).ready( function ( $ ) {

    // enable watermark for
    $( document ).on( 'change', '#df_option_everywhere, #df_option_cpt', function () {
		if ( $( '#cpt-specific input[type=radio]:checked' ).val() === 'everywhere' ) {
			$( '#cpt-select' ).fadeOut( 300 );
		} else if ( $( '#cpt-specific input[type=radio]:checked' ).val() === 'specific' ) {
			$( '#cpt-select' ).fadeIn( 300 );
		}
	} );

	$( document ).on( 'click', '#reset_ultimate_watermark_options', function () {
		return confirm( ulwmArgs.resetToDefaults );
    } );

    // size slider
    $( '#ulwm_size_span' ).slider( {
		value: $( '#ulwm_size_input' ).val(),
		min: 0,
		max: 100,
		step: 1,
		orientation: 'horizontal',
		slide: function ( e, ui ) {
			$( '#ulwm_size_input' ).attr( 'value', ui.value );
			$( '#ulwm_size_span' ).attr( 'title', ui.value );

			var element = $( ui.handle ).prev( '.ulwm-current-value' );

			element.text( ui.value );
			element.css( 'left', ui.value + '%' );
		}
    } );

    // opacity slider
    $( '#ulwm_opacity_span' ).slider( {
		value: $( '#ulwm_opacity_input' ).val(),
		min: 0,
		max: 100,
		step: 1,
		orientation: 'horizontal',
		slide: function ( e, ui ) {
			$( '#ulwm_opacity_input' ).attr( 'value', ui.value );
			$( '#ulwm_opacity_span' ).attr( 'title', ui.value );

			var element = $( ui.handle ).prev( '.ulwm-current-value' );

			element.text( ui.value );
			element.css( 'left', ui.value + '%' );
		}
    } );

    // quality slider
    $( '#ulwm_quality_span' ).slider( {
		value: $( '#ulwm_quality_input' ).val(),
		min: 0,
		max: 100,
		step: 1,
		orientation: 'horizontal',
		slide: function ( e, ui ) {
			$( '#ulwm_quality_input' ).attr( 'value', ui.value );
			$( '#ulwm_quality_span' ).attr( 'title', ui.value );

			var element = $( ui.handle ).prev( '.ulwm-current-value' );

			element.text( ui.value );
			element.css( 'left', ui.value + '%' );
		}
    } );

    // quality slider
    $( '#ulwm_backup_quality_span' ).slider( {
		value: $( '#ulwm_backup_quality_input' ).val(),
		min: 0,
		max: 100,
		step: 1,
		orientation: 'horizontal',
		slide: function ( e, ui ) {
			$( '#ulwm_backup_quality_input' ).attr( 'value', ui.value );
			$( '#ulwm_backup_quality_span' ).attr( 'title', ui.value );

			var element = $( ui.handle ).prev( '.ulwm-current-value' );

			element.text( ui.value );
			element.css( 'left', ui.value + '%' );
		}
    } );

} );
