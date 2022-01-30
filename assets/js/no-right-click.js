/*
 This javascript is used by the no-right-click-images plugin for wordpress.
 Version 2.2
 Please give credit as no-right-click-images.js by Keith P. Graham
 http://www.blogseye.com
 */

var ulwmNRCtargImg = null;
var ulwmNRCtargSrc = null;
var ulwmNRCinContext = false;
var ulwmNRCnotimage = new Image();
var ulwmNRClimit = 0;
var ulwmNRCextra = ulwmNRCargs.rightclick;
var ulwmNRCdrag = ulwmNRCargs.draganddrop;

function ulwmNRCdragdropAll( event ) {
	try {
		var ev = event || window.event;
		var targ = ev.srcElement || ev.target;
		if ( targ.tagName.toUpperCase() == "A" ) {
			// is this IE and are we dragging a link to the image?
			var hr = targ.href;
			hr = hr.toUpperCase();
			if ( hr.indexOf( '.JPG' ) || hr.indexOf( '.PNG' ) || hr.indexOf( '.GIF' ) ) {
				ev.returnValue = false;
				if ( ev.preventDefault ) {
					ev.preventDefault();
				}
				ulwmNRCinContext = false;
				return false;
			}
		}
		if ( targ.tagName.toUpperCase() != "IMG" )
			return true;
		ev.returnValue = false;
		if ( ev.preventDefault ) {
			ev.preventDefault();
		}
		ulwmNRCinContext = false;
		return false;
	} catch ( er ) {
		// alert(er);
	}
	return true;
}

function ulwmNRCdragdrop( event ) {
	// I am beginning to doubt if this event ever fires
	try {
		var ev = event || window.event;
		var targ = ev.srcElement || ev.target;
		ev.returnValue = false;
		if ( ev.preventDefault ) {
			ev.preventDefault();
		}
		ev.returnValue = false;
		ulwmNRCinContext = false;
		return false;
	} catch ( er ) {
		// alert(er);
	}
	return true;
}

function ulwmNRCcontext( event ) {
	try {
		ulwmNRCinContext = true;
		var ev = event || window.event;
		var targ = ev.srcElement || ev.target;
		ulwmNRCreplace( targ );
		ev.returnValue = false;
		if ( ev.preventDefault ) {
			ev.preventDefault();
		}
		ev.returnValue = false;
		ulwmNRCtargImg = targ;
	} catch ( er ) {
		// alert(er);
	}
	return false;
}

function ulwmNRCcontextAll( event ) {
	try {
		if ( ulwmNRCtargImg == null ) {
			return true;
		}
		ulwmNRCinContext = true;
		var ev = event || window.event;
		var targ = ev.srcElement || ev.target;
		if ( targ.tagName.toUpperCase() == "IMG" ) {
			ev.returnValue = false;
			if ( ev.preventDefault ) {
				ev.preventDefault();
			}
			ev.returnValue = false;
			ulwmNRCreplace( targ );
			return false;
		}
		return true;
	} catch ( er ) {
		// alert(er);
	}
	return false;
}

function ulwmNRCmousedown( event ) {
	try {
		ulwmNRCinContext = false;
		var ev = event || window.event;
		var targ = ev.srcElement || ev.target;
		if ( ev.button == 2 ) {
			ulwmNRCreplace( targ );
			return false;
		}
		ulwmNRCtargImg = targ;
		if ( ulwmNRCdrag == 'Y' ) {
			if ( ev.preventDefault ) {
				ev.preventDefault();
			}
		}
		return true;
	} catch ( er ) {
		// alert(er);
	}
	return true;
}

function ulwmNRCmousedownAll( event ) {
	try {
		ulwmNRCinContext = false;
		var ev = event || window.event;
		var targ = ev.srcElement || ev.target;
		if ( targ.style.backgroundImage != '' && ev.button == 2 ) {
			targ.oncontextmenu = function ( event ) {
				return false;
			} // iffy - might not work
		}
		if ( targ.tagName.toUpperCase() == "IMG" ) {
			if ( ev.button == 2 ) {
				ulwmNRCreplace( targ );
				return false;
			}
			if ( ulwmNRCdrag == 'Y' ) {
				if ( ev.preventDefault ) {
					ev.preventDefault();
				}
			}
			ulwmNRCtargImg = targ;
		}
		return true;
	} catch ( er ) {
		// alert(er);
	}
	return true;
}

function ulwmNRCreplace( targ ) {
	return false;
	if ( ulwmNRCtargImg != null && ulwmNRCtargImg.src == ulwmNRCnotimage.src ) {
		// restore the old image before hiding this one
		ulwmNRCtargImg.src = ulwmNRCtargSrc;
		ulwmNRCtargImg = null;
		ulwmNRCtargSrc = null;
	}
	ulwmNRCtargImg = targ;
	if ( ulwmNRCextra != 'Y' )
		return;
	var w = targ.width + '';
	var h = targ.height + '';
	if ( w.indexOf( 'px' ) <= 0 )
		w = w + 'px';
	if ( h.indexOf( 'px' ) <= 0 )
		h = h + 'px';
	ulwmNRCtargSrc = targ.src;
	targ.src = ulwmNRCnotimage.src;
	targ.style.width = w;
	targ.style.height = h;
	ulwmNRClimit = 0;
	var t = setTimeout( "ulwmNRCrestore()", 500 );
	return false;
}

function ulwmNRCrestore() {
	if ( ulwmNRCinContext ) {
		if ( ulwmNRClimit <= 20 ) {
			ulwmNRClimit++;
			var t = setTimeout( "ulwmNRCrestore()", 500 );
			return;
		}
	}
	ulwmNRClimit = 0;
	if ( ulwmNRCtargImg == null )
		return;
	if ( ulwmNRCtargSrc == null )
		return;
	ulwmNRCtargImg.src = ulwmNRCtargSrc;
	ulwmNRCtargImg = null;
	ulwmNRCtargSrc = null;
	return;
}

// set the image onclick event
// need to check for dblclick to see if there is a right double click in IE
function ulwmNRCaction( event ) {
	try {
		document.onmousedown = function ( event ) {
			return ulwmNRCmousedownAll( event );
		}
		document.oncontextmenu = function ( event ) {
			return ulwmNRCcontextAll( event );
		}
		document.oncopy = function ( event ) {
			return ulwmNRCcontextAll( event );
		}
		if ( ulwmNRCdrag == 'Y' )
			document.ondragstart = function ( event ) {
				return ulwmNRCdragdropAll( event );
			}
		var b = document.getElementsByTagName( "IMG" );
		for ( var i = 0; i < b.length; i++ ) {
			b[i].oncontextmenu = function ( event ) {
				return ulwmNRCcontext( event );
			}
			b[i].oncopy = function ( event ) {
				return ulwmNRCcontext( event );
			}
			b[i].onmousedown = function ( event ) {
				return ulwmNRCmousedown( event );
			}
			if ( ulwmNRCdrag == 'Y' )
				b[i].ondragstart = function ( event ) {
					return ulwmNRCdragdrop( event );
				}
		}
	} catch ( er ) {
		return false;
	}
}

if ( document.addEventListener ) {
	document.addEventListener( "DOMContentLoaded", function ( event ) {
		ulwmNRCaction( event );
	}, false );
} else if ( window.attachEvent ) {
	window.attachEvent( "onload", function ( event ) {
		ulwmNRCaction( event );
	} );
} else {
	var oldFunc = window.onload;
	window.onload = function () {
		if ( oldFunc ) {
			oldFunc();
		}
		ulwmNRCaction( 'load' );
	};
}