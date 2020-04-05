<?php

namespace Zofe\Rapyd\DataForm\Field;

use Collective\Html\FormFacade as Form;
use Input;
use Rapyd;

class Map extends Field
{
    public $type = 'map';
    public $lat = 'lat';
    public $lon = 'lon';
    public $zoom = 12;
    public $key;

    public function latlon($lat, $lon)
    {
        $this->lat = $lat;
        $this->lon = $lon;

        return $this;
    }

    public function zoom($zoom)
    {
        $this->zoom = $zoom;

        return $this;
    }

    public function key($key)
    {
        $this->key = $key;

        return $this;
    }

    public function autoUpdate($save = false)
    {
        if (isset($this->model)) {
            $this->getValue();
            $this->getNewValue();
            $this->model->setAttribute($this->lat, $this->new_value['lat']);
            $this->model->setAttribute($this->lon, $this->new_value['lon']);
            if ($save) {
                return $this->model->save();
            }
        }

        return true;
    }

    public function getValue()
    {
        $process = (Input::get('search') || Input::get('save')) ? true : false;

        if (true == $this->request_refill && $process && Input::exists($this->lat)) {
            $this->value['lat'] = Input::get($this->lat);
            $this->value['lon'] = Input::get($this->lon);
            $this->is_refill = true;
        } elseif (('create' == $this->status) && (null != $this->insert_value)) {
            $this->value = $this->insert_value;
        } elseif (('modify' == $this->status) && (null != $this->update_value)) {
            $this->value = $this->update_value;
        } elseif (isset($this->model)) {
            $this->value['lat'] = $this->model->getAttribute($this->lat);
            $this->value['lon'] = $this->model->getAttribute($this->lon);
            $this->description = implode(',', array_values($this->value));
        }
    }

    public function getNewValue()
    {
        $process = (Input::get('search') || Input::get('save')) ? true : false;
        if ($process && Input::exists($this->lat)) {
            $this->new_value['lat'] = Input::get($this->lat);
            $this->new_value['lon'] = Input::get($this->lon);
        } elseif (('insert' == $this->action) && (null != $this->insert_value)) {
            $this->edited = true;
            $this->new_value = $this->insert_value;
        } elseif (('update' == $this->action) && (null != $this->update_value)) {
            $this->edited = true;
            $this->new_value = $this->update_value;
        }
    }

    public function build()
    {
        $output = '';
        $this->attributes['class'] = 'form-control';
        if (false === parent::build()) {
            return;
        }

        switch ($this->status) {
            case 'disabled':
            case 'show':

                if ('hidden' == $this->type || '' == $this->value) {
                    $output = '';
                } elseif ((!isset($this->value))) {
                    $output = $this->layout['null_label'];
                } else {
                    $output = "<img border=\"0\" src=\"//maps.googleapis.com/maps/api/staticmap?center={$this->value['lat']},{$this->value['lon']}&zoom={$this->zoom}&size=500x500\">";
                }
                $output = "<div class='help-block'>" . $output . '</div>';
                break;

            case 'create':
            case 'modify':
                $output = Form::hidden($this->lat, $this->value['lat'], ['id' => $this->lat]);
                $output .= Form::hidden($this->lon, $this->value['lon'], ['id' => $this->lon]);
                $output .= '<div id="map_' . $this->name . '" style="width:500px; height:500px"></div>';
                $output .= '<script src="' . $this->getUrl() . '"></script>';

                Rapyd::script("
        
            function initialize()
            {
                var latitude = document.getElementById('{$this->lat}');
                var longitude = document.getElementById('{$this->lon}');
                var zoom = {$this->zoom};
        
                var LatLng = new google.maps.LatLng(latitude.value, longitude.value);
        
                var mapOptions = {
                    zoom: zoom,
                    center: LatLng,
                    panControl: false,
                    zoomControl: true,
                    scaleControl: true,
                    mapTypeId: google.maps.MapTypeId.ROADMAP
                }
        
                var map = new google.maps.Map(document.getElementById('map_{$this->name}'),mapOptions);
                var marker = new google.maps.Marker({
                    position: LatLng,
                    map: map,
                    title: 'Drag Me!',
                    draggable: true
                });

                var update_hidden_fields = function () {
                    latitude.value = marker.getPosition().lat();
                    longitude.value = marker.getPosition().lng();
                }
                google.maps.event.addListener(marker, 'dragend', update_hidden_fields);

                $(document.getElementById('map_{$this->name}')).data('map', map);
                $(document.getElementById('map_{$this->name}')).data('marker', marker);
                $(document.getElementById('map_{$this->name}')).data('update_hidden_fields', update_hidden_fields);
            }
            initialize();
        ");

                break;

            case 'hidden':
                $output = ''; //Form::hidden($this->db_name, $this->value);
                break;

            default:
        }
        $this->output = "\n" . $output . "\n" . $this->extra_output . "\n";
    }

    public function getUrl()
    {
        $url = 'https://maps.googleapis.com/maps/api/js?v=3.exp';
        if ($this->key) {
            $url .= '&key=' . $this->key;
        }

        return $url;
    }
}
