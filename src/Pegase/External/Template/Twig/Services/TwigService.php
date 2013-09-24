<?php

namespace Pegase\External\Template\Twig\Services;

use \Pegase\Core\Service\Service\ServiceInterface;
use \Pegase\External\Template\Twig\Extensions\Path\PathFunction;
use \Pegase\External\Template\Twig\Extensions\Router\RouteFunction;
use \Pegase\External\Template\Twig\Extensions\Controller\RenderFunction;

use Pegase\Core\Exception\Objects\PegaseException;

class TwigService implements ServiceInterface {

  private $sm;
  private $loader;
  private $twig;

  public function __construct($sm, $params = array()) {

    // soit on utilise ça
    //require_once (__DIR__ .  '/../../../../../../vendor/twig/twig/lib/Twig/Autoloader.php');
    //\Twig_Autoloader::register();

    // soit le require_once '../../../../../../vendor/autoload.php'; de composer
    // -> c'est fait !

    // initialisation
    $this->sm = $sm;
    
    $root = $sm->get('pegase.core.path')->get_root();

    $this->loader = new \Twig_Loader_Filesystem($root);//__DIR__ . '/../../../../../../');
    $this->twig = new \Twig_Environment(
      $this->loader,
      array('debug' => true, 'strict_variables' => true)
      /*, 
      array('cache' => $root . 'app/cache/twig')*/
    );

    $this->twig->addExtension(new \Twig_Extension_Debug());

    // ajout de la fonction "path"

    $test = new PathFunction($sm);
    $function = new \Twig_SimpleFunction($test->get_name(), array($test, 'fn'));
    $this->twig->addFunction($function);
    // activer le cache en retirant les commentaires

    // ajout de la fonction "route"

    $test = new RouteFunction($sm);
    $function = new \Twig_SimpleFunction($test->get_name(), array($test, 'fn'));
    $this->twig->addFunction($function);

    // ajout de la fonction "Render"

    $test = new RenderFunction($sm);
    $function = new \Twig_SimpleFunction($test->get_name(), array($test, 'fn'));
    $this->twig->addFunction($function);
  }

  public function render($file, $params = array()) {
  
    // Quand on envoie une exception dans le template twig,
    // twig la catch ! Il faut catcher l'exception twig ...
    // et renvoyer l'exception
    try {
      $ret = $this->twig->render($file, $params);
    }
    catch(\Twig_Error $e) {
      if($e->getPrevious() == null) // si c'est une exception de twig
        throw $e;
      else
        throw new PegaseException($e->getPrevious());
    }

    return $ret;
  }
}

