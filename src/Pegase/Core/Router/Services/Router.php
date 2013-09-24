<?php

namespace Pegase\Core\Router\Services;
use Pegase\Core\Service\Service\ServiceInterface;

use Pegase\Core\Exception\Objects\PegaseException;

class Router implements ServiceInterface {
  
  private $routes;
  private $sm;

  public function __construct($sm, $params = array()) {
    /*$this->routes = array(
      'pegase_test' => array('/test', '\supertest\Demo', 'index')
    );*/
    $this->routes = array();
    $this->sm = $sm;

    // chargement de 2 services
    $yaml = $this->sm->get('pegase.component.yaml.spyc');
    
    $data = $yaml->parse('/app/config/routing.yml');
    
    //echo "<pre>";
    //var_dump($data);
    //echo "</pre>";

    foreach($data as $route_name => $route) {

      if(key_exists('pattern', $route) && 
         key_exists('controller', $route) && 
         key_exists('method', $route))
      {
        $this->routes[$route_name] = array($route['pattern'], $route['controller'], $route['method']);
      }
      else if(isset($route['import']) && isset($route['prefix'])) {
        $subdata = $yaml->parse($route['import']);
        
        $n = strlen($route['prefix']) - 1;
        if($n >= 0) {
          if($route['prefix'][$n] == '/')
            $route['prefix'] = substr($route['prefix'], 0, $n);
          else;
        }
        else {
          $route['prefix'] = "";
        }

        foreach($subdata as $sub_route_name => $sub_route) {
          if(isset($sub_route['pattern']) && 
             isset($sub_route['controller']) && 
             isset($sub_route['method']))
          {
            $this->routes[$sub_route_name] = array(
              $route['prefix'] . $sub_route['pattern'], 
              $sub_route['controller'], 
              $sub_route['method']
            );

            //echo $this->routes[$sub_route_name][0] . '<br />';
            //echo $route['prefix'] . $sub_route['pattern'] . '<br />';
          }
          else {
            echo "Router: Fichier de sous-routes inexistant";
          }
        }
      }
    }
  }

  public function instancy_controller($name) {
    $controller = NULL;
  
    if(class_exists($name))  
      $controller = new $name($this->sm);
    else
      echo "Router: Erreur: the Class `$name` n'existe pas.<br />";

    return $controller;
  }

  // get:
  // returns the controller with 'route_name'

  public function get($route_name) {
    $route = null;

    if(!array_key_exists($route_name, $this->routes))
      echo "Router: error: the array key $route_name doesn't exists.<br />";
    else { 
      $route_infos = $this->routes[$route_name];
      
      if($route_controller = $this->instancy_controller($route_infos[1])) {
        $route = array($route_controller, $route_infos[2]);
      }
    }
   
    return $route;
  }

  public function get_with_uri($uri) {
    
    $route = null;

    // en cas de requête GET, on doit enlever les paramètres: '/mon/pattern?mes_params=32&...'
    $t = $uri;
    $t = explode('?', $t);
    $uri = $t[0];

    foreach($this->routes as $r) {

        // on traite le pattern
        $pattern = $r[0];
        $pattern = str_replace('/', '\\/', $pattern);
        $pattern = preg_replace('/{(?:.+?)}/', '([0-9a-zA-z_]+?)', $pattern); 
        // soit ?; soit option U

        // On doit enlever le dernier (...?) pour le remplacer par (...?)
        // sinon on ne match pas jusqu'au bout de l'URI
      
        $l = strlen($pattern);
        $sub = substr($pattern, $l - strlen('([0-9a-zA-z_]+?)'));

        if($sub == "([0-9a-zA-z_]+?)") {
          $pattern[$l - 2] = ')';
          $pattern = substr($pattern, 0, $l - 1);
        }

        preg_match("/" . $pattern . "/", $uri, $matches2);

        $pattern = $r[0];

        if(count($matches2) > 0) {
          $verif = str_replace($uri, '', $matches2[0]);
        }
        else
          $verif = 'r';
   
      
      if($verif == '') { // si l'uri correspond bien à la requête 
        $route_infos = $r;
 
        // 1) on récupère les paramètres
        array_shift($matches2); // on enlève le premier élément
        $params = $this->get_params($pattern, $matches2);
        
        if($route_controller = $this->instancy_controller($route_infos[1])) {
          $route = array($route_controller, $route_infos[2], $params);
        }

        break;
      }
    }

    return $route;
  }

  // génère le chemin vers une route
  public function generate($route_name, $params = array()) {

    $path_s = $this->sm->get('pegase.core.path');

    if(!array_key_exists($route_name, $this->routes))
      throw new PegaseException(
        "The array key <span style='color:blue;'>`$route_name`</span> doesn't exists.<br />"
      );
    else {
      
      $route_infos = $this->routes[$route_name];
      
      $path = $path_s->get_html_path($route_infos[0]);
      $path = '/' . $path;
  
      $path = $this->replace_with_params($path, $params);
      //$path = $_SERVER['REQUEST_URI'] . $path;
      $path = substr($path, 1);
      
    }

    return $path;
  }
  
  private function get_params($pattern, $matched_values) {
    $params = array();

    preg_match_all('/{(.+)}/U', $pattern, $matches);

    foreach($matches[1] as $i => $param) {
      $params[$param] = $matched_values[$i];
    }

    return $params;
  }

  private function replace_with_params($pattern, $params) {
    preg_match_all("/{(.+)}/U", $pattern, $matches);

    $path = $pattern;

    foreach($matches[1] as $match) {
      if(!key_exists($match, $params))
        ; //echo "Le paramètre '$match' est manquant !\n";
      else
        $path = @preg_replace('/{' . $match . '}/', $params[$match], $path);
    }

    return $path;
  }
}

