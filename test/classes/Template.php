<?php
/**
 * Fichier de test pour une class
 */

namespace BFWTpl\test\unit;
use \atoum;

require_once(__DIR__.'/../common.php');

/**
 * Test de la class Template
 */
class Template extends atoum
{
    /**
     * @var $class : Instance de la class Template
     */
    protected $class;

    /**
     * @var $mock : Instance du mock pour la class Template
     */
    protected $mock;

    /**
     * Instanciation de la class avant chaque méthode de test
     */
    public function beforeTestMethod($testMethod)
    {
        //$this->class = new \BFWTpl\Template();
        //$this->mock  = new MockTemplate();
    }

    /**
     * Test de la méthode getBlock()
     */
    public function testGetBlock()
    {
        
    }
    
    /**
     * Test de la méthode getFileLink()
     */
    public function testGetFileLink()
    {
        
    }
    
    /**
     * Test de la méthode getTamponFinal()
     */
    public function testGetTamponFinal()
    {
        
    }
    
    /**
     * Test de la méthode getRoot_Variable()
     */
    public function testGetRoot_Variable()
    {
        
    }
    
    /**
     * Test de la méthode getGen_Variable()
     */
    public function testGetGen_Variable()
    {
        
    }
    
    /**
     * Test de la méthode getBanWords()
     */
    public function testGetBanWords()
    {
        
    }

    /**
     * Test du constructeur : Template($file, $vars=)
     */
    public function testTemplate()
    {
        
    }

    /**
     * Test de la méthode EndBlock()
     */
    public function testEndBlock()
    {
        
    }

    /**
     * Test de la méthode AddGeneralVars($vars)
     */
    public function testAddGeneralVars()
    {
        
    }

    /**
     * Test de la méthode AddVars($vars, $name=)
     */
    public function testAddVars()
    {
        
    }

    /**
     * Test de la méthode remonte()
     */
    public function testRemonte()
    {
        
    }

    /**
     * Test de la méthode AddBlockWithEnd($name, $varsOrEnd=, $end=)
     */
    public function testAddBlockWithEnd()
    {
        
    }

    /**
     * Test de la méthode AddBlock($name, $vars=, $end=)
     */
    public function testAddBlock()
    {
        
    }

    /**
     * Test de la méthode End($no_echo=0)
     */
    public function testEnd()
    {
        
    }

}

/**
 * Mock pour la classe Template
 */
class MockTemplate extends \BFWTpl\Template
{
    /**
     * Accesseur get
     */
    public function __get($name) {return $this->$name;}
}
