<?php include './config.php'; ?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8" />
        <title><?=$config['title']?></title>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.4.0/dist/leaflet.css" integrity="sha512-puBpdR0798OZvTTbP4A8Ix/l+A4dHDD0DGqYW6RQ+9jxkRFclaxxQb/SJAWZfWAkuyeQUytO7+7N4QKrDh+drA==" crossorigin=""/>
        <script src="https://unpkg.com/leaflet@1.4.0/dist/leaflet.js" integrity="sha512-QVftwZFqvtRNi0ZyCtsznlKSWOStnDORoefr1enyq5mVL4tmKB3S/EnC3rRJcxCPavG10IcrVGSmPh6Qw5lwrg==" crossorigin=""></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
    </head>
    <body><div id="mapid" style="top: 0; left: 0; position: absolute; height: 100%; width: 100%;"></div></body>
</html>

<script>
// TODO: Scrollable legend

const startLat = <?=$config['startLat']?>;
const startLon = <?=$config['startLon']?>;
const startZoom = <?=$config['startZoom']?> || 10;
const minZoom = <?=$config['minZoom']?> || 10;
const maxZoom = <?=$config['maxZoom']?> || 18;
const areas = <?=json_encode($config['areas'])?>;
const tileserver = "<?=$config['tileserver']?>";

// Layers
const polygonLayer = new L.LayerGroup();

// Map
const map = L.map('mapid', {
    preferCanvas: true,
    layers: [polygonLayer],
}).setView([startLat, startLon], startZoom);
L.tileLayer(tileserver, {
    minZoom: minZoom,
    maxZoom: maxZoom,
}).addTo(map);

// Information
const info = L.control();
info.onAdd = function (map) {
    this._div = L.DomUtil.create('div', 'info');
    this.update();
    return this._div;
};
info.update = function (props) {
    this._div.innerHTML = '<h4>Area Selected:</h4>' + (props ? 
        `<b>${props.name}</b> (${props.size} km<sup>2</sup>)` :
        'Hover over a city');
};
info.addTo(map);

loadScanAreaPolygons();

// Legend
let geojson;
let legend = L.control({position: 'topright'});
legend.onAdd = function (map) {
    let div = L.DomUtil.create('div', 'info legend');
    let html = '';
    html += '<span><b>' + areas.length + ' total cities</b></span><hr>';
    for (let i = 0; i < areas.length; i++) {
        const area = areas[i];
        /*
        html += `
        <a href="#" onclick="centerMap(${properties.center.lat},${properties.center.lng})">&ndash; ${area.city}</a>
        <br>`;
        */
        html += `
        <a href="#">&ndash; ${area.city}</a>
        <br>`;
    }
    div.innerHTML += html;
    return div;
};
legend.addTo(map);

function centerMap(lat, lng) {
    map.setView([lat, lng], 13)
}

function style(feature) {
    return {
        weight: 2,
        opacity: 1,
        color: 'white',
        dashArray: '3',
        fillOpacity: 0.7,
        fillColor: feature.properties.color
	};
}

function highlightFeature(e) {
    let layer = e.target;
    layer.setStyle({
        weight: 4,
        color: '#666',
        dashArray: '',
        fillOpacity: 0.7
    });
    if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
        layer.bringToFront();
    }
    info.update(layer.feature.properties);
}

function resetHighlight(e) {
    geojson.resetStyle(e.target);
    info.update();
}

function zoomToFeature(e) {
    map.fitBounds(e.target.getBounds());
}

function geodesicArea(latLngs) {
    let pointsCount = latLngs.length,
        area = 0.0,
        d2r = Math.PI / 180,
        p1, p2;
    if (pointsCount > 2) {
        for (let i = 0; i < pointsCount; i++) {
            p1 = latLngs[i];
            p2 = latLngs[(i + 1) % pointsCount];
            area += ((p2[0] - p1[0]) * d2r) * // lng
                (2 + Math.sin(p1[1] * d2r) + Math.sin(p2[1] * d2r)); // lat
        }
        area = area * 6378137.0 * 6378137.0 / 2.0;
    }
    return Math.abs(area);
}

function convertAreaToSqkm(value) {
    return value * 1.0E-6;
}

function getRandomColor() {
    let letters = '0123456789ABCDEF';
    let color = '#';
    for (let i = 0; i < 6; i++) {
        color += letters[Math.floor(Math.random() * 16)];
    }
    return color;
}

function loadScanAreaPolygons () {
    $.getJSON('./areas.json', function (data) {
        try {
            geojson = L.geoJson(data, {
                style: style,
                onEachFeature: function(features, featureLayer) {
                    if (!features.properties.hidden) {
                        const coords = features.geometry.coordinates[0];
                        const areaSize = geodesicArea(coords);
                        const size = convertAreaToSqkm(areaSize).toFixed(2);
                        featureLayer.on({
                            mouseover: highlightFeature,
                            mouseout: resetHighlight,
                            click: zoomToFeature
                        });
                        features.properties.size = size;
                        features.properties.color = features.properties.color || getRandomColor();
                        featureLayer.setStyle({
                            weight: 2,
                            opacity: 1,
                            color: 'white',
                            dashArray: '3',
                            fillOpacity: 0.7,
                            fillColor: features.properties.color,
                        });
                        featureLayer.bindPopup(getScanAreaPopupContent(features.properties, size));
                    }
                }
            });
            polygonLayer.addLayer(geojson);
        } catch (err) {
            console.error('Failed to load areas.json file\nError:', err);
        }
    });
}

function getScanAreaPopupContent(properties, size) {
    const content = `
      <center>
        <h6>Area: <b>${properties.name}</b></h6>
        Size: ${size} km<sup>2</sup>
      </center>`;
    return content;
}
</script>

<style>
.info {
    padding: 6px 8px;
    font: 14px/16px Arial, Helvetica, sans-serif;
    background: white;
    background: rgba(255,255,255,0.8);
    box-shadow: 0 0 15px rgba(0,0,0,0.2);
    border-radius: 5px;
    width: 150px;
    height: 55px;
    border: 1px solid black;
}
.info h4 {
    margin: 0 0 5px;
    color: #777;
}
.legend {
    text-align: left;
    line-height: 18px;
    height: 480px;
    overflow-y: auto;
}
.legend a {
    color: black;
    width: 18px;
    height: 18px;
    margin-right: 8px;
    opacity: 0.7;
    text-decoration: none;
}
.legend a:hover {
    color: white;
    background-color: dodgerblue;
}
::-webkit-scrollbar {
    -webkit-appearance: none;
    width: 7px;
}
::-webkit-scrollbar-thumb {
    border-radius: 4px;
    background-color: rgba(0, 0, 0, .5);
    -webkit-box-shadow: 0 0 1px rgba(255, 255, 255, .5);
}
</style>