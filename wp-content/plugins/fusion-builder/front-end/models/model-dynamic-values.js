/* global FusionEvents, FusionApp, fusionBuilderText */

var FusionPageBuilder = FusionPageBuilder || {};

( function() {

	FusionPageBuilder.DynamicValues = Backbone.Model.extend( {
		defaults: {
			values: {},
			options: {},
			orderedParams: false
		},

		getOrderedParams: function() {
			var params  = this.get( 'orderedParams' ),
				options = this.getOptions();

			if ( ! params ) {
				params = {};
				_.each( options, function( object, id ) {
					var group,
						groupText;

					if ( 'object' !== typeof object ) {
						return;
					}

					group     = object.group;
					groupText = group;

					if ( 'string' !== typeof object.group ) {
						group     = 'other';
						groupText = fusionBuilderText.other;
					}

					group = group.replace( /\s+/g, '_' ).toLowerCase();

					if ( 'object' !== typeof params[ group ] ) {
						params[ group ] = {
							label: '',
							params: {}
						};
					}

					params[ group ].label        = groupText;
					params[ group ].params[ id ] = object;
				} );
			}
			return params;
		},

		addData: function( data, options ) {
			this.set( 'values', data );
			this.set( 'options', options );
		},

		getOptions: function() {
			var options = this.get( 'options' );

			return jQuery.extend( true, {}, options );
		},

		getOption: function( param ) {
			var options = this.getOptions();

			return 'undefined' !== typeof options[ param ] ? options[ param ] : false;
		},

		getAll: function() {
			var values = this.get( 'values' );

			return jQuery.extend( true, {}, values );
		},

		getValue: function( args ) {
			var values   = this.getAll(),
				id       = args.data,
				idValues = 'object' === typeof values[ id ] ? values[ id ] : false,
				match    = false;

			// No initial match, fetch it.
			if ( ! idValues ) {
				return this.fetchValue( id, args );
			}

			// Check each value object with same ID.
			match = this.findMatch( idValues, args );

			// We found a matching object, then return its value.
			if ( match ) {
				return match.value;
			}

			// No match, fetch.
			return this.fetchValue( id, args );
		},

		findMatch: function( idValues, args, idWanted ) {
			var match = false;

			idWanted = 'undefined' === typeof idWanted ? false : idWanted;

			_.each( idValues, function( idValue, idCount ) {
				var argsMatch = true;

				// Already found a match, just return early.
				if ( match ) {
					return true;
				}

				// Value object has no args, then set match and return.
				if ( 'undefined' === typeof idValue.args ) {
					match = idWanted ? idCount : idValue;
					return true;
				}

				// We do have args, check that each value matches.
				if ( 'object' === typeof idValue.args ) {
					_.each( idValue.args, function( argValue, argId ) {
						if ( 'undefined' === typeof args[ argId ] || 'before' === argId || 'after' === argId || 'fallback' === argId ) {
							return true;
						}
						if ( args[ argId ] !== argValue ) {
							argsMatch = false;
						}
					} );

					if ( argsMatch ) {
						match = idWanted ? idCount : idValue;
					}
				}
			} );
			return match;
		},

		fetchValue: function( id, args ) {
			var options          = this.getOptions(),
				param            = 'object' === typeof options && 'object' === typeof options[ id ] ? options[ id ] : false,
				callback         = param && 'undefined' !== typeof param.callback ? param.callback : false,
				callbackFunction = callback && 'string' === typeof callback[ 'function' ] ? callback[ 'function' ] : false,
				callbackExists   = callbackFunction && 'function' === typeof FusionApp.callback[ callbackFunction ] ? true : false,
				callbackAjax     = callbackExists && 'undefined' !== typeof callback.ajax ? callback.ajax : false,
				value;

			if ( ! param || ! callbackExists ) {
				this.setValue( args, false );
				return false;
			}

			if ( callbackAjax ) {
				return FusionApp.callback[ callbackFunction ]( args );
			}

			value = FusionApp.callback[ callbackFunction ]( args );
			this.setValue( args, value );
			return value;
		},

		setValue: function( args, value ) {
			var values   = this.getAll(),
				id       = args.data,
				existing = jQuery.extend( true, {}, values[ id ] ),
				matchId  = false,
				newData  = {
					args: jQuery.extend( true, {}, args ),
					value: value
				};

			if ( 'object' !== typeof values[ id ] ) {
				values[ id ] = [];
			} else if ( 'function' !== typeof values[ id ].push ) {
				values[ id ] = [ existing[ 0 ] ];
			}

			matchId = this.findMatch( values[ id ], args, true );

			if ( ! matchId ) {
				values[ id ].push( newData );
			} else {
				values[ id ][ matchId ] = newData;
			}

			this.set( 'values', values );

			// ReRender the element.  Perhaps via event using id.
			FusionEvents.trigger( 'fusion-dynamic-data-value', id );
		},

		removeValue: function( id ) {
			var values = this.getAll();

			if ( 'object' === typeof values[ id ] ) {
				delete values[ id ];
			}
			this.set( 'values', values );
		}
	} );
}( jQuery ) );
