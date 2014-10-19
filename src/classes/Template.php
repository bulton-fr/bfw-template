<?php
/**
 * Classes en rapport avec le système de template
 * @author Vermeulen Maxime <bulton.fr@gmail.com>
 * @version 1.0
 */

namespace BFWTpl;

/**
 * Système de template
 * @package bfw-template
 */
class Template implements \BFWTplInterface\ITemplate
{
    /**
     * @var $_kernel L'instance du Kernel
     */
    protected $_kernel;
    
    /**
     * @var $FileLink Le lien du fichier
     */
    protected $FileLink = '';
    
    /**
     * @var $TamponFinal Tampon contenant le résultat final
     */
    protected $TamponFinal = '';
    
    /**
     * @var $Block Infos sur les blocks
     */
    protected $Block = array();
    
    /**
     * @var $Root_Variable Les variables n'étant pas dans un block
     */
    protected $Root_Variable = array();
    
    /**
     * @var $Gen_Variable Les variables générales
     */
    protected $Gen_Variable = array();
    
    /**
     * @var $CurrentBlock L'adresse du block en cours
     */
    public $CurrentBlock = '/';
    
    /**
     * @var $BanWords Les mots interdits
     */
    protected $BanWords = array('block', 'vars');
    
    /*********************************************
     * Note sur le déplacement dans les blocks : *
     *********************************************
     * On ne connais pas à l'avance le nombre de sous-block.
     * Ainsi $this->Block peut avoir de nombreux sous-array et on ne connais pas à l'avance la position de chacun.
     * On utilise $this->CurrentBlock pour savoir où on en est.
     * L'information stocké dans $this->CurrentBlock ressemble au chemin dans les dossiers sous Unix.
     * De plus, dans $this->Block, pour chaque block, on stock d'abord le numéro de boucle, pour pour chacun, 
     * un array avec les variables puis un array avec les blocks.
     * 
     * Exemple de $this->Block
     * Array(
     *  'block1' => Array(
     *      0 => Array(
     *          'vars' => Array(),
     *          'block' => Array(
     *              'block2' => Array(
     *                  0 => Array(
     *                      'vars' => Array(),
     *                      'block' => Array()
     *                  ),
     *                  1 => Array(
     *                      'vars' => Array(),
     *                      'block' => Array()
     *                  )
     *              )
     *          )
     *      )
     *  )
     * )
     *
     * Exemple de $this->CurrentBlock
     * On est pas dans un block             : /
     * On est dans 'block1'                 : /block1/0
     * On est dans 'block2' (1er boucle)    : /block1/0/block/block2/0
     * On est dans 'block2' (2nde boucle)   : /block1/0/block/block2/1
     * 
     * Le principe de boucle étant que lorsqu'un block est déclaré dans une boucle php, 
     * on créer une nouvelle boucle dans la structure de $this->Block
     */
    
    /**
     * Accesseur get vers l'attribut $Block
     * 
     * @return array
     */
    public function getBlock() {return $this->Block;}
    
    /**
     * Accesseur get vers l'attribut $FileLink
     * 
     * @return array
     */
    public function getFileLink() {return $this->FileLink;}
    
    /**
     * Accesseur get vers l'attribut $TamponFinal
     * 
     * @return array
     */
    public function getTamponFinal() {return $this->TamponFinal;}
    
    /**
     * Accesseur get vers l'attribut $Root_Variable
     * 
     * @return array
     */
    public function getRoot_Variable() {return $this->Root_Variable;}
    
    /**
     * Accesseur get vers l'attribut $Gen_Variable
     * 
     * @return array
     */
    public function getGen_Variable() {return $this->Gen_Variable;}
    
    /**
     * Accesseur get vers l'attribut $BanWords
     * 
     * @return array
     */
    public function getBanWords() {return $this->BanWords;}
    
    /**
     * Construteur
     * 
     * @param string     $file Le lien vers le fichier tpl
     * @param array|null $vars (default: null) Des variables n'étant pas dans un block à passer (nom => valeur)
     */
    public function __construct($file, $vars=null)
    {
        $this->_kernel = getKernel();
        $this->FileLink = path_view.$file;
        
        if($vars != null) //Si on a mis des variables en paramètre, on les envoi à AddVars();
        {
            $this->AddVars($vars);
        }
    }
    
    /**
     * A indiquer à la fin de l'utilisation du 1er block.
     * Permet de revenir au chemin racine dans l'arborescence pour les blocks suivant,
     * de façon à ce qu'il ne soit pas mis comme un sous-block du dernier block ouvert
     */
    public function EndBlock()
    {
        $this->CurrentBlock = '/';
    }
    
    /**
     * Permet d'ajouter une variable à une liste qui sera lu partout, qu'on soit dans un block ou non
     * 
     * @param array $vars Les variables à ajouter (nom => valeur)
     */
    public function AddGeneralVars($vars)
    {
        if(is_array($vars))
        {
            foreach($vars as $key => $val)
            {
                $this->Gen_Variable[$key] = $val;
            }
        }
    }
    
    /**
     * Ajoute des variables à un block ou non
     * 
     * @param array       $vars Les variables à ajouter (nom => valeur)
     * @param bool|string $name (default: false) Indique si c'est pour un block (le block courant est utilisé)
     * Il est aussi possible de donner le nom du block, cependant il est préférable de
     * le faire sur des block qui sont des conditions et non des blocks boucle.
     */
    public function AddVars($vars, $name=false)
    {
        //On vérifie que se soit bien un array
        if(!is_array($vars)) {return;}
        
        //On doit ajoute des vars à un block
        if($name != false)
        {
            $block = $this->positionneVarsToBlock($name, $TabVars);
            
            //Utilisation d'un nom de block qui ne doit pas être utilisé.
            if($block === false) {return false;}
        }
        //On n'est pas dans un block, $TabVars prend la référence de $this->Root_Variable
        else {$TabVars = &$this->Root_Variable;}
        
        //On ajoute une par une toute les variables qui ont été données.
        foreach($vars as $key => $val)
        {
            $TabVars[$key] = $val;
        }
    }
    
    /**
     * Permet de se position dans un block bien précis et d'en retourner le tableau contenant les variables
     * 
     * 
     * @param bool|string $name (default: false) Indique si c'est pour un block (le block courant est utilisé)
     * Il est aussi possible de donner le nom du block, cependant il est préférable de
     * le faire sur des block qui sont des conditions et non des blocks boucle.
     * 
     * @return null|false
     */
    protected function positionneVarsToBlock($name, &$vars)
    {
        //Objectif : Se positionner dans le tableau des Block. On ne connais pas le nombre de sous block à l'avance
        
        //On place $TabVars à la racine du tableau. L'utilisation d'une référence permet 
        //d'agir directement sur le contenu.
        $TabVars = &$this->Block;
        
        //On n'est pas dans le 1er block
        if($this->CurrentBlock == '/')
        {
            $vars = &$TabVars;
            return;
        }
        
        //On nous a indiquer le block auquel on doit s'ajouter 
        //(à ne faire que sur un block n'étant pas une boucle !!)
        if($name != true)
        {
            //Sécurité par rapport à des noms de block qui ne doivent pas être utilisé
            if(in_array($name, $this->BanWords)) {return false;}
            
            //On regarde si le block existe
            $pos = strpos($this->CurrentBlock, '/'.$name.'/');
            
            //On prend l'adresse jusqu'au block voulu
            $current = substr($this->CurrentBlock, 0, $pos);
        }
        else //On prend le block courant
        {
            //Triche pour passer la vérification en dessous
            $pos = 1;
            
            //On prend le block courant
            $current = $this->CurrentBlock;
            
            //On ne connais pas son nom mais ce n'est pas grave, 
            //il est utile que si on nous l'a indiqué
            $name = '';
        }
        
        //Si le block existe bien
        if($pos === false)
        {
            $vars = &$TabVars;
            return;
        }
        
        //Utile pour le cas où on nous donne le nom du block.
        //Permet de savoir si le block a été trouvé durant la lecture.
        $find = false;
        $exCurrent = explode('/', $current); //On découpe le chemin
        
        //On lit chaque morceau du chemin un par un
        foreach($exCurrent as $val)
        {
            //Si le nom est vide.
            if($val == '') {continue;}
            
            //On position $TabVars vers la référence du
            //sous-tableau qu'on lit par rapport à la où on est
            $TabVars = &$TabVars[$val];
            
            //Si le block qu'on cherchait à été trouvé à la lecture précédente on quitte le foreach
            //Il est utile de le faire à la boucle après le nom du tableau 
            //de façon à se positionner dans la 1er boucle.
            if($find == true) {break;}
            
            //Si le block qu'on lit possède le même nom que celui qu'on recherche, 
            //on indique l'avoir trouvé.
            if($val == $name) {$find = true;}
        }
        
        //Puis on place $TabVars vers la référence du sous-array 'vars' par rapport à la où on est.
        $vars = &$TabVars['vars'];
    }
    
    /**
     * Permet de remonter dans les blocks
     */
    public function remonte()
    {
        $ex = explode('/', $this->CurrentBlock);
        $cnt_ex = count($ex);
        
        $BoucleLastBlock = $cnt_ex-1;
        $nameLastBlock = $cnt_ex-2;
        $ifBlock = $cnt_ex-3;
        
        unset($ex[$BoucleLastBlock], $ex[$nameLastBlock]);
        
        if($ex[$ifBlock] == 'block')
        {
            unset($ex[$ifBlock]);
        }
        
        if(count($ex) == 1 && $ex[0] == '')
        {
            $this->CurrentBlock = '/';
        }
        else
        {
            $this->CurrentBlock = implode('/', $ex);
        }
    }
    
    /**
     * Ajoute un sous block au système et appelle méthode EndBlock() à la fin
     * 
     * @param string    $name      Le nom du block
     * @param array|int $varsOrEnd (default: null) Les variables du block à passer (nom => valeur). Si int voir 3eme paramètre
     * @param int       $end       (default: null) Indique de combien de block on doit remonter
     */
    public function AddBlockWithEnd($name, $varsOrEnd = null, $end=null)
    {
        if(is_array($varsOrEnd))
        {
            $this->AddBlock($name, $varsOrEnd);
        }
        else
        {
            $this->AddBlock($name);
        }
        
        if(is_int($varsOrEnd) || $end != null)
        {
            if($end == null)
            {
                $end = $varsOrEnd;
            }
            
            for($i=0;$i<$end;$i++)
            {
                $this->remonte();
            }
        }
        else
        {
            $this->EndBlock();
        }
    }
    
    /**
     * Ajoute un sous block au système
     * 
     * @param string $name Le nom du block
     * @param array  $vars (default: null) Les variables du block à passer (nom => valeur)
     * @param int    $end  (default: null) Indique de combien de block on doit remonter
     * 
     * @return bool Retourne true si tout c'est bien passé, False si le nom du block n'est pas autorisé.
     */
    public function AddBlock($name, $vars = null, $end=null)
    {
        //Sécurité par rapport à des noms de block qui ne doivent pas être utilisé
        if(in_array($name, $this->BanWords)) {return false;}
        
        //Initialise
        
        //Par défaut on dit être dans la 1ere boucle du block
        $boucle  = 0;
        
        //On positionne $Tab vers la référence de $this->Block
        $Tab     = &$this->Block;
        
        //Initialise le curseur indiquant le block courant
        $current = $current = $this->CurrentBlock.$name;
        
        //On n'est pas dans le 1er block
        if($this->CurrentBlock != '/')
        {
            positionneToBlock($name, $current, $boucle, $Tab);
        }
        else //On est à la racine
        {
            //Le block existe déjà, on cherche la dernière boucle
            if(isset($Tab[$name][$boucle]))
            {
                $boucle = count($Tab[$name]);
            }
        }
        
        //On ajoute l'arborescence d'un block à $Tab dans le sous-Array correspondant à notre block
        //et la boucle voulu (créé si existe pas).
        $Tab[$name][$boucle] = array(
            'vars' => array(),
            'block' => array()
        );
        
        //On met à jour $this->CurrentBlock avec le block qu'on vient de créer et sa boucle.
        $this->CurrentBlock = $current.'/'.$boucle;
        
        //Si des variable on été passé, on envoi ça à $this->AddVars()
        if(is_array($vars)) {$this->AddVars($vars, true);}
        
        //S'il faut remonter dans les blocs
        if(!is_null($end))
        {
            //On remonte de n block
            for($i=0; $i<$end; $i++) {$this->remonte();}
        }
        
        return true; //Tout est ok.
    }
    
    /**
     * Permet de positionner le curseur sur le block voulu
     * 
     * @param string $name     Le nom du block
     * @param string &$current L'emplacement courant du block dans la structure
     * @param int    &$boucle  Le nombre de boucle du block
     * @param array  &$Tab     Le tableau du block lu
     * 
     * @return void
     */
    protected function positionneToBlock($name, &$current, &$boucle, &$Tab)
    {
        $pos = strpos($this->CurrentBlock, '/'.$name.'/'); //On regarde si le block existe déjà
        
        //On découpe le chemin
        $exCurrent = explode('/', $current);
        
        //S'il existe déjà dans l'emplacement où on est dans l'arborescence, on ajoute une boucle dedans
        if($pos !== false)
        {
            //On récupère le chemin direct vers l'array 'block' du block parent à celui voulu
            $current = substr($this->CurrentBlock, 0, $pos);
        }
        //Sinon c'est que c'est un nouveau block. On doit le créer dans l'arborescence
        else
        {
            //On positionne le chemin vers le sous block 'block' du block courrant
            $current = $this->CurrentBlock.'/block';
        }
        
        //On y ajoute le nom de notre block dans le chemin
        $current .= '/'.$name;
        
        //On positionne $Tab vers une référence vers le sous-array que l'on souhaite
        foreach($exCurrent as $val)
        {
            if($val != '') {$Tab = &$Tab[$val];}
        }
        
        //On compte le nombre d'élément contient l'array de notre block
        //afin de connaitre le numéro de boucle suivant.
        $boucle = count($Tab[$name]);
    }
    
    /**
     * Indique la fin du fichier template.
     * Une fois appelé, le script parse le fichier template.
     * 
     * @param integer $no_echo (default: 0) Indique s'il faut afficher le résultat par echo (défault) ou le renvoyer en sortie de fonction
     * 
     * @return string Retourne le résultat du parsage si $no_echo=1
     */
    public function End($no_echo=0)
    {
        $this->CurrentBlock = '/'; //On place le chemin à la racine
        
        $parse = new Parse($this);
        return $parse->run($no_echo);
    }
    
    /**
     * Retourne les variables global ou pour un block en particulier
     * 
     * @param string|bool $nameBlock Le chemin dans l'arborescence où on se trouve
     * 
     * @return array
     */
    public function getVars($nameBlock=false)
    {
        //On initialise $TabVars avec $this->Root_Variable
        $Tab = $this->Root_Variable;
        
        //Si on est dans un block.
        if(is_string($nameBlock) && $nameBlock != '/')
        {
            //On positionne $Tab vers la référence de $this->Block
            $Tab = &$this->Block;
            
            //On découpe le chemin
            $exCurrent = explode('/', $nameBlock);
            
            //On positionne $Tab vers une référence vers le sous-array que l'on souhaite
            foreach($exCurrent as $val)
            {
                if($val != '') {$Tab = &$Tab[$val];}
            }
            
            //Puis on place $TabVars vers la référence du sous-array 'vars' par rapport à la où on est.
            $Tab = &$Tab['vars'];
        }
        
        return $Tab;
    }
}