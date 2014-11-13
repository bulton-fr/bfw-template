<?php
/**
 * Fichier de test pour une class
 */

namespace BFWTpl\test\unit;
use \atoum;

require_once(__DIR__.'/../common.php');

/**
 * Test de la class Parse
 */
class Parse extends atoum
{
    /**
     * @var $class : Instance de la class Parse
     */
    protected $class;

    /**
     * @var $mock : Instance du mock pour la class Parse
     */
    protected $mock;

    /**
     * Instanciation de la class avant chaque méthode de test
     */
    public function beforeTestMethod($testMethod)
    {
        //$this->class = new \BFWTpl\Parse();
        //$this->mock  = new MockParse();
    }

    /**
     * Test du constructeur : Parse(Template $tpl)
     */
    public function testParse()
    {
        
    }

    /**
     * Test de la méthode run($no_echo=0)
     */
    public function testRun()
    {
        
    }

}

/**
 * Mock pour la classe Template
 */
class MockParse extends \BFWTpl\Parse
{
    /**
     * Accesseur get
     */
    public function __get($name) {return $this->$name;}
}
