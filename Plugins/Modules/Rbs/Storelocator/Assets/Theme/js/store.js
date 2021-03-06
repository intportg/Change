(function(jQuery) {
	"use strict";
	var app = angular.module('RbsChangeApp');

	function rbsStorelocatorStore($rootScope, $compile, AjaxAPI) {
		return {
			restrict: 'A',
			templateUrl: '/rbsStorelocatorStore.tpl',
			scope: {
				cacheKey: '@'
			},
			controller: ['$scope', '$element', function(scope, elem) {
				var init = AjaxAPI.globalVar(scope.cacheKey);
				scope.parameters = AjaxAPI.getBlockParameters(scope.cacheKey);

				this.getStoreData = function() {
					return init.storeData;
				}
			}],

			link : function(scope, elem, attrs, controller) {
				scope.storeData = controller.getStoreData();

				if (scope.storeData.coordinates) {
					var latLng = {lat: scope.storeData.coordinates.latitude, lng: scope.storeData.coordinates.longitude};
					scope.map = L.map('map-' + scope.cacheKey, {center: latLng, zoom: 11});
					var l = new L.TileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
					scope.map.addLayer(l);
					scope.marker = L.marker(latLng, {}).addTo(scope.map);
				}

				scope.chooseStore = function(store) {
					scope.$emit('rbsStorelocatorChooseStore', store.common.id);
				};

			}
		}
	}

	rbsStorelocatorStore.$inject = ['$rootScope', '$compile', 'RbsChange.AjaxAPI'];
	app.directive('rbsStorelocatorStore', rbsStorelocatorStore);

	function rbsStorelocatorGoogleStore(AjaxAPI) {
		return {
			restrict: 'A',
			templateUrl: '/rbsStorelocatorStore.tpl',
			scope: {
				cacheKey: '@'
			},
			controller: ['$scope', '$element', function(scope, elem) {
				var init = AjaxAPI.globalVar(scope.cacheKey);

				this.getStoreData = function() {
					return init.storeData;
				}
			}],

			link : function(scope, elem, attrs, controller) {
				scope.storeData = controller.getStoreData();

				if (scope.storeData.coordinates) {
					var latLng = new google.maps.LatLng(scope.storeData.coordinates.latitude, scope.storeData.coordinates.longitude);
					var mapOptions = {
						center: latLng,
						zoom: 11
					};
					scope.map = new google.maps.Map(document.getElementById('map-' + scope.cacheKey), mapOptions);

					var markerOptions = {position: latLng, map: scope.map};
					scope.marker = new google.maps.Marker(markerOptions);
				}

				scope.chooseStore = function(store) {
					scope.$emit('rbsStorelocatorChooseStore', store.common.id);
				};
			}
		}
	}

	rbsStorelocatorGoogleStore.$inject = ['RbsChange.AjaxAPI'];
	app.directive('rbsStorelocatorGoogleStore', rbsStorelocatorGoogleStore);

	function rbsStorelocatorSearch($rootScope, $compile, AjaxAPI, $timeout, $templateCache) {
		return {
			restrict: 'A',
			templateUrl: '/rbsStorelocatorSearch.tpl',
			scope: {
				cacheKey: '@'
			},
			controller: ['$scope', '$element', function(scope, elem) {

				var controllerInit = AjaxAPI.globalVar(scope.cacheKey);
				scope.parameters =  AjaxAPI.getBlockParameters(scope.cacheKey);

				scope.markers = null;
				scope.markerIcon = null;
				scope.markerIconUrl = null;

				if (controllerInit.markerIcon) {
					scope.markerIconUrl = controllerInit.markerIcon.url;
					scope.markerIcon = L.icon({
						iconUrl : scope.markerIconUrl,
						iconAnchor: L.point(controllerInit.markerIcon.width / 2, controllerInit.markerIcon.height)
					});
				}

				this.getSearchContext = function() {
					return controllerInit ? controllerInit['searchContext'] : null;

				};

				this.getInitialStoresData = function() {
					return controllerInit && controllerInit['storesData'] ? controllerInit['storesData'] : null;
				};

				this.search = function(data) {
					var params = this.getSearchContext();
					var request = AjaxAPI.getData('Rbs/Storelocator/Store/', data, params);
					request.success(function() {
						scope.$emit('rbsStorelocatorSearchHome', true);
					});
					return request;
				};


				this.setMarkers = function(stores, viewCenter) {
					var latLngCenter = L.latLng(viewCenter[0], viewCenter[1]);
					if (scope.markers) {
						scope.markers.clearLayers();
					}

					if (stores.length) {
						if (!scope.map) {
							scope.map = L.map('map-' + scope.cacheKey, {center: latLngCenter, zoom: 11});
							var l = new L.TileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png');
							scope.map.addLayer(l);
							scope.markers = new L.FeatureGroup();
							scope.map.addLayer(scope.markers);
							$timeout(function() {
								scope.map.invalidateSize(false);
							}, 150);
						} else {
							scope.map.setView(latLngCenter);
						}

						var options = scope.markerIcon ? {icon: scope.markerIcon} : {};
						angular.forEach(stores, function(store) {
							var latLng = L.latLng(store.coordinates.latitude, store.coordinates.longitude);

							var marker = L.marker(latLng, options);

							var html = '<div class="marker-popup-content" id="store-marker-' + store.common.id + '"></div>';
							marker.bindPopup(html, {minWidth:220, offset:L.point(0, -(scope.markerIcon ? controllerInit.markerIcon.height - 5: 30))});

							marker.on('click', function(e){
								marker.openPopup();
							});

							marker.on('popupopen', function(e){
								var html = '<div data-rbs-storelocator-store-popup=""></div>';
								scope.popupStore = store;
								var popupContent = elem.find('#store-marker-' + store.common.id);
								$compile(html)(scope, function (clone) {
									popupContent.append(clone);
								});
							});

							marker.on('popupclose', function(e){
								scope.popupStore = null;
								var popupContent = elem.find('#store-marker-' + store.common.id);
								var collection = popupContent.children();
								collection.each(function() {
									var isolateScope = angular.element(this).isolateScope();
									if (isolateScope) {
										isolateScope.$destroy();
									}
								});
								collection.remove();
							});

							store.coordinates.marker = marker;
							scope.markers.addLayer(marker);
						});
					}
				};
			}],

			link : function(scope, elem, attrs, controller) {
				scope.stores = null;
				scope.searchAddress = null;
				scope.formatedAddress = null;
				scope.filteredAddress = null;
				scope.myCoordinates = null;
				scope.addressCoordinates = null;
				scope.error = null;
				scope.searchContext = controller.getSearchContext();
				scope.loadingAddresses = false;
				scope.country = 'France';
				scope.defaultDistance = '50km';
				scope.distance = '50km';
				scope.options = {};

				if (scope.parameters.commercialSignId) {
					scope.commercialSignId = scope.parameters.commercialSignId;
				} else {
					scope.commercialSignId = null;
				}

				scope.showHome = function () {
					return scope.stores === null;
				};

				scope.searchInSignStores = function () {
					scope.commercialSignId = scope.parameters.commercialSignId;
					scope.search();
				};

				scope.searchInAllStores = function () {
					scope.commercialSignId = null;
					scope.search();
				};

				scope.search = function () {
					var coordinates = null;
					if (scope.myCoordinates) {
						coordinates = scope.myCoordinates;
					} else if (scope.addressCoordinates) {
						coordinates = scope.addressCoordinates;
					}

					if (coordinates) {
						if (scope.map) {
							var latLng = L.latLng(coordinates.latitude, coordinates.longitude);
							scope.map.setView(latLng);
						}
						scope.addressLoading = true;
						controller.search({coordinates:coordinates, distance: scope.distance,
							commercialSign: scope.commercialSignId ? scope.commercialSignId : 0})
							.success(function(data) {
								scope.addressLoading = false;
								scope.stores = data.items;
								controller.setMarkers(scope.stores, [coordinates.latitude, coordinates.longitude]);
							}).error(function(data, status) {
								scope.addressLoading = false;
								scope.stores = [];
							});
					}
				};

				scope.locateMe = function() {
					scope.error = null;
					scope.addressLoading = true;
					navigator.geolocation.getCurrentPosition(
						function (position) {
							scope.addressLoading = false;
							scope.formatedAddress = null;
							scope.addressCoordinates = null;
							scope.myCoordinates = {latitude: position.coords.latitude, longitude: position.coords.longitude};
							scope.distance = scope.defaultDistance;
							scope.search();
						},
						function (error) {
							scope.addressLoading = false;
							scope.error = 2;
							alert("Localisation failed : [" + error.code + "] " + error.message);
						}, {timeout: 5000, maximumAge: 0}
					);
				};

				scope.viewStoreOnMap = function(store) {
					var latLng = L.latLng(store.coordinates.latitude, store.coordinates.longitude);
					scope.map.setView(latLng);
					store.coordinates.marker.openPopup();

				};

				scope.showStoreDetail = function(store) {
					window.location.href = store.common.URL.canonical;
				};

				scope.chooseStore = function(store) {
					scope.$emit('rbsStorelocatorChooseStore', store.common.id);
				};

				// POC: Auto-complete address.
				scope.getLoadedAddresses = function(val) {
					return AjaxAPI.getData('Rbs/Geo/AddressCompletion/',
						{address: val, countryCode: scope.country, options:scope.options})
						.then(function(res) {
							if (res.status == 200) {
								var searchAddress = scope.searchAddress, items = res.data.items;
								angular.forEach(items, function(item) {
									if (item.title == searchAddress) {
										searchAddress = null;
									}
								});
								if (searchAddress) {
									items.splice(0, 0, {title: searchAddress});
								}
								return items;
							}
							return [];
						});
				};

				scope.selectLoadedAddress = function($event) {
					if (scope.addressLoading) {
						return;
					}

					if ($event) {
						if ($event.keyCode != 13) {
							return;
						}
					}

					scope.addressLoading = true;
					scope.error = null;
					var address = {lines:['', scope.searchAddress, scope.country]};
					AjaxAPI.getData('Rbs/Geo/CoordinatesByAddress', {address: address}).success(function(data) {
						scope.addressLoading = false;
						if (data.dataSets && data.dataSets.latitude) {
							scope.myCoordinates = null;
							scope.filteredAddress = null;
							scope.addressCoordinates = {latitude: data.dataSets.latitude, longitude: data.dataSets.longitude};
							scope.formatedAddress = data.dataSets.formattedAddress || address.lines[1];
							scope.distance = scope.defaultDistance;
							scope.search();
						} else {
							scope.stores = [];
							scope.error = 1;
						}
					}).error(function(data) {
						scope.addressLoading = false;
						scope.error = 1;
					});
				};

				scope.updateDistance = function(distance) {
					scope.distance = distance;
					scope.search();
				};

				scope.$watchCollection('stores', function(stores, old) {
					if (stores === null && stores === old) {
						var initialStoresData = controller.getInitialStoresData();
						if (angular.isArray(initialStoresData)) {
							scope.stores = initialStoresData;
							scope.filteredAddress = attrs.facetValueTitle;
							scope.$emit('rbsStorelocatorSearchHome', true);
							if (scope.stores.length) {

								controller.setMarkers(scope.stores, [scope.stores[0].coordinates.latitude, scope.stores[0].coordinates.longitude]);
								var bounds = [], store= null, latLng = null, fitBounds = null;
								angular.forEach(initialStoresData, function(store){
									bounds.push(L.latLng(store.coordinates.latitude, store.coordinates.longitude));
								});
								fitBounds = L.latLngBounds(bounds);
								$timeout(function() {
									scope.map.fitBounds(fitBounds, {padding: L.point(0, 30)});
								}, 250);
							}
						}
					}
				})
			}
		}
	}
	rbsStorelocatorSearch.$inject = ['$rootScope', '$compile', 'RbsChange.AjaxAPI', '$timeout', '$templateCache'];
	app.directive('rbsStorelocatorSearch', rbsStorelocatorSearch);

	function rbsStorelocatorSearchHome($rootScope) {
		return {
			restrict: 'A',
			link : function(scope, elem, attrs) {
				scope.hideStorelocatorSearchHome = false;
				$rootScope.$on('rbsStorelocatorSearchHome', function(e, hideHome) {
					scope.hideStorelocatorSearchHome = hideHome;
				})
			}
		}
	}
	rbsStorelocatorSearchHome.$inject = ['$rootScope'];
	app.directive('rbsStorelocatorSearchHome', rbsStorelocatorSearchHome);

	function rbsStorelocatorStoreItem() {
		return {
			restrict: 'A',
			templateUrl: '/rbsStorelocatorStoreItem.tpl',
			link: function (scope, elem, attrs, controler) {
				scope.getDistance = function() {
					if (scope.storeData.coordinates.distance) {
						return (Math.round(scope.storeData.coordinates.distance * 10) / 10) + ' ' + scope.storeData.coordinates.distanceUnite;
					}
					return '';
				}
			}
		}
	}
	app.directive('rbsStorelocatorStoreItem', rbsStorelocatorStoreItem);

	function rbsStorelocatorStorePopup() {
		return {
			restrict: 'A',
			templateUrl: '/rbsStorelocatorStorePopup.tpl',
			link: function (scope, elem, attrs, controler) {
			}
		}
	}
	app.directive('rbsStorelocatorStorePopup', rbsStorelocatorStorePopup);

	function rbsStorelocatorGoogleSearch($compile, AjaxAPI, $timeout) {
		return {
			restrict: 'A',
			templateUrl: '/rbsStorelocatorSearch.tpl',
			scope: {
				cacheKey: '@'
			},
			controller: ['$scope', '$element', function(scope, elem) {

				var controllerInit = AjaxAPI.globalVar(scope.cacheKey);
				scope.parameters =  AjaxAPI.getBlockParameters(scope.cacheKey);

				scope.markers = null;
				scope.markerIcon = null;
				scope.markerIconUrl = null;

				if (controllerInit.markerIcon) {
					scope.markerIconUrl = controllerInit.markerIcon.url;
					scope.markerIcon = {
						url: scope.markerIconUrl,
						size: new google.maps.Size(controllerInit.markerIcon.width, controllerInit.markerIcon.height),
						origin: new google.maps.Point(0,0),
						anchor: new google.maps.Point(controllerInit.markerIcon.width / 2, controllerInit.markerIcon.height)
					}
				}

				this.getSearchContext = function() {
					return controllerInit ? controllerInit['searchContext'] : null;
				};

				this.getInitialStoresData = function() {
					return controllerInit && controllerInit['storesData'] ? controllerInit['storesData'] : null;
				};

				this.search = function(data) {
					var params = this.getSearchContext();
					var request = AjaxAPI.getData('Rbs/Storelocator/Store/', data, params);
					request.success(function() {
						scope.$emit('rbsStorelocatorSearchHome', true);
					});
					return request;
				};

				this.setMarkers = function(stores, viewCenter) {
					if (scope.markers) {
						for (var i = 0; i < scope.markers.length; i++) {
							scope.markers[i].setMap(null);
						}
					}
					scope.markers = [];
					if (stores.length) {
						if (!scope.map) {
							var mapOptions = {
								center: new google.maps.LatLng(viewCenter[0], viewCenter[1]),
								zoom: 11
							};
							scope.map = new google.maps.Map(document.getElementById('map-' + scope.cacheKey), mapOptions);
						} else {
							scope.map.setCenter(new google.maps.LatLng(viewCenter[0], viewCenter[1]));
						}

						angular.forEach(stores, function(store) {
							var latLng = new google.maps.LatLng(store.coordinates.latitude, store.coordinates.longitude);
							var markerOptions = {position: latLng, map: scope.map};
							if (scope.markerIcon) {
								markerOptions.icon = scope.markerIcon;
							}
							var marker = new google.maps.Marker(markerOptions);
							scope.markers.push(marker);

							store.coordinates.openPopup = function() {
								if (scope.infoWindow) {
									scope.infoWindow.close();
									scope.popupStore = null;
								}
								var html = '<div data-rbs-storelocator-store-popup="" style="height: 100px; min-width: 180px"></div>';
								scope.infoWindow = new google.maps.InfoWindow({
									content: $compile(html)(scope)[0]
								});
								google.maps.event.addListener(scope.infoWindow, 'domready', function() {
									scope.popupStore = store;
									scope.$digest();
								});
								scope.infoWindow.open(scope.map, marker);
							};
							google.maps.event.addListener(marker, 'click', function() {
								store.coordinates.openPopup();
							});
						});
					}
				};
			}],

			link : function(scope, elem, attrs, controller) {
				scope.stores = null;
				scope.searchAddress = null;
				scope.formatedAddress = null;
				scope.filteredAddress = null;
				scope.myCoordinates = null;
				scope.addressCoordinates = null;
				scope.error = null;
				scope.searchContext = controller.getSearchContext();
				scope.loadingAddresses = false;
				scope.country = 'France';
				scope.defaultDistance = '50km';
				scope.distance = '50km';
				scope.options = {};

				if (scope.parameters.commercialSignId) {
					scope.commercialSignId = scope.parameters.commercialSignId;
				} else {
					scope.commercialSignId = null;
				}

				scope.showHome = function () {
					return scope.stores === null;
				};

				scope.searchInSignStores = function () {
					scope.commercialSignId = scope.parameters.commercialSignId;
					scope.search();
				};

				scope.searchInAllStores = function () {
					scope.commercialSignId = null;
					scope.search();
				};

				scope.search = function () {
					var coordinates = null;
					if (scope.myCoordinates) {
						coordinates = scope.myCoordinates;
					} else if (scope.addressCoordinates) {
						coordinates = scope.addressCoordinates;
					}

					if (coordinates) {
						if (scope.map) {
							scope.map.setCenter(new google.maps.LatLng(coordinates.latitude, coordinates.longitude));
						}
						scope.addressLoading = true;
						controller.search({coordinates:coordinates, distance: scope.distance,
							commercialSign: scope.commercialSignId ? scope.commercialSignId : 0})
							.success(function(data) {
								scope.addressLoading = false;
								scope.stores = data.items;
								controller.setMarkers(scope.stores, [coordinates.latitude, coordinates.longitude]);
							}).error(function(data, status) {
								scope.addressLoading = false;
								scope.stores = [];
							});
					}
				};

				scope.locateMe = function() {
					scope.error = null;
					scope.addressLoading = true;
					navigator.geolocation.getCurrentPosition(
						function (position) {
							scope.addressLoading = false;
							scope.formatedAddress = null;
							scope.addressCoordinates = null;
							scope.myCoordinates = {latitude: position.coords.latitude, longitude: position.coords.longitude};
							scope.distance = scope.defaultDistance;
							scope.search();
						},
						function (error) {
							scope.addressLoading = false;
							scope.error = 2;
							alert("Localisation failed : [" + error.code + "] " + error.message);
						}, {timeout: 5000, maximumAge: 0}
					);
				};

				scope.viewStoreOnMap = function(store) {
					var latLng = new google.maps.LatLng(store.coordinates.latitude, store.coordinates.longitude);
					scope.map.setCenter(latLng);
					store.coordinates.openPopup();
				};

				scope.showStoreDetail = function(store) {
					window.location.href = store.common.URL.canonical;
				};

				scope.chooseStore = function(store) {
					scope.$emit('rbsStorelocatorChooseStore', store.common.id);
				};

				scope.selectLoadedAddress = function() {
					var place = scope.autocomplete.getPlace();
					if (!place.geometry) {
						return;
					}
					scope.error = null;
					scope.myCoordinates = null;
					scope.filteredAddress = null;
					var location = place.geometry.location;
					scope.addressCoordinates = {latitude: location.lat(), longitude: location.lng()};
					scope.formatedAddress = place.formatted_address;
					scope.distance = scope.defaultDistance;
					scope.search();
				};

				scope.updateDistance = function(distance) {
					scope.distance = distance;
					scope.search();
				};

				scope.$watchCollection('stores', function(stores, old) {
					if (stores === null && stores === old) {

						scope.autocomplete = new google.maps.places.Autocomplete(
							(document.getElementById(scope.cacheKey + '_autocomplete')), { types: ['geocode'] });
						// When the user selects an address from the dropdown,
						// populate the address fields in the form.
						google.maps.event.addListener(scope.autocomplete, 'place_changed', function() {
							scope.selectLoadedAddress();
						});

						var initialStoresData = controller.getInitialStoresData();
						if (angular.isArray(initialStoresData)) {
							scope.stores = initialStoresData;
							scope.filteredAddress = attrs.facetValueTitle;
							scope.$emit('rbsStorelocatorSearchHome', true);
							if (scope.stores.length) {
								var bounds = new google.maps.LatLngBounds();
								angular.forEach(initialStoresData, function(store){
									bounds.extend(new google.maps.LatLng(store.coordinates.latitude, store.coordinates.longitude));
								});
								var center = bounds.getCenter();
								controller.setMarkers(scope.stores, [center.lat(), center.lng()]);
								scope.map.fitBounds(bounds);
							}
						}
					}
				})
			}
		}
	}
	rbsStorelocatorGoogleSearch.$inject = ['$compile', 'RbsChange.AjaxAPI', '$timeout'];
	app.directive('rbsStorelocatorGoogleSearch', rbsStorelocatorGoogleSearch);

})(jQuery);