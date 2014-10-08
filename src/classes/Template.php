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
    public function getBlock()
    {
        return $this->Block;
    }
    
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
        if(is_array($vars)) //On vérifie que se soit bien un array
        {
            if($name != false) //On doit ajoute des vars à un block
            {
                //Objectif : Se positionner dans le tableau des Block. On ne connais pas le nombre de sous block à l'avance
                
                //On place $TabVars à la racine du tableau. L'utilisation d'une référence permet 
                //d'agir directement sur le contenu.
                $TabVars = &$this->Block;
                
                if($this->CurrentBlock != '/') //On n'est pas dans le 1er block
                {
                    //On nous a indiquer le block auquel on doit s'ajouter 
                    //(à ne faire que sur un block n'étant pas une boucle !!)
                    if($name != true)
                    {
                        //Sécurité par rapport à des noms de block qui ne doivent pas être utilisé
                        if(in_array($name, $this->BanWords))
                        {
                            return false;
                        }
                        
                        //On regarde si le block existe
                        $pos = strpos($this->CurrentBlock, '/'.$name.'/');
                        
                        //On prend l'adresse jusqu'au block voulu
                        $current = substr($this->CurrentBlock, 0, $pos);
                    }
                    else //On prend le block courant
                    {
                        $pos = 1; //Triche pour passer la vérification en dessous
                        $current = $this->CurrentBlock; //On prend le block courant
                        
                        //On ne connais pas son nom mais ce n'est pas grave, 
                        //il est utile que si on nous l'a indiqué
                        $name = '';
                    }
                    
                    if($pos !== false) //Si le block existe bien
                    {
                        //Utile pour le cas où on nous donne le nom du block.
                        //Permet de savoir si le block a été trouvé durant la lecture.
                        $find = false;
                        $exCurrent = explode('/', $current); //On découpe le chemin

                        //On lit chaque morceau du chemin un par un
                        foreach($exCurrent as $val)
                        {
                            if($val != '') //Si le nom n'est pas vide.
                            {
                                //On position $TabVars vers la référence du
                                //sous-tableau qu'on lit par rapport à la où on est
                                $TabVars = &$TabVars[$val];
                                
                                //Si le block qu'on cherchait à été trouvé à la lecture précédente on quitte le foreach
                                //Il est utile de le faire à la boucle après le nom du tableau 
                                //de façon à se positionner dans la 1er boucle.
                                if($find == true)
                                {
                                    break;
                                }
                                
                                //Si le block qu'on lit possède le même nom que celui qu'on recherche, 
                                //on indique l'avoir trouvé.
                                if($val == $name)
                                {
                                    $find = true;
                                }
                            }
                        }
                        
                        //Puis on place $TabVars vers la référence du sous-array 'vars' par rapport à la où on est.
                        $TabVars = &$TabVars['vars'];
                    }
                }
            }
            else //On n'est pas dans un block, $TabVars prend la référence de $this->Root_Variable
            {
                $TabVars = &$this->Root_Variable;
            }
            
            //On ajoute une par une toute les variables qui ont été données.
            foreach($vars as $key => $val)
            {
                $TabVars[$key] = $val;
            }
        }
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
        if(in_array($name, $this->BanWords))
        {
            return false;
        }
        
        $boucle = 0; //Par défaut on dit être dans la 1ere boucle du block
        $Tab = &$this->Block; //On positionne $Tab vers la référence de $this->Block
        
        if($this->CurrentBlock != '/') //On n'est pas dans le 1er block
        {
            $pos = strpos($this->CurrentBlock, '/'.$name.'/'); //On regarde si le block existe déjà
            
            //S'il existe déjà dans l'emplacement où on est dans l'arborescence, on ajoute une boucle dedans
            if($pos !== false)
            {
                //On récupère le chemin direct vers l'array 'block' du block parent à celui voulu
                $current = substr($this->CurrentBlock, 0, $pos);
                $exCurrent = explode('/', $current); //On découpe le chemin
                
                //On positionne $Tab vers une référence vers le sous-array que l'on souhaite
                foreach($exCurrent as $val)
                {
                    if($val != '')
                    {
                        $Tab = &$Tab[$val];
                    }
                }
                
                $current .= '/'.$name; //On y ajoute le nom de notre block dans le chemin
                
                //On compte le nombre d'élément contient l'array de notre block
                //afin de connaitre le numéro de boucle suivant.
                $boucle = count($Tab[$name]);
            }
            else //Sinon c'est que c'est un nouveau block. On doit le créer dans l'arborescence
            {
                //On positionne le chemin vers le sous block 'block' du block courrant
                $current = $this->CurrentBlock.'/block';
                $exCurrent = explode('/', $current); //On découpe le chemin
                
                //On positionne $Tab vers une référence vers le sous-array que l'on souhaite
                foreach($exCurrent as $val)
                {
                    if($val != '')
                    {
                        $Tab = &$Tab[$val];
                    }
                }
                
                $current .= '/'.$name; //On y ajoute le nom de notre block dans le chemin
                
                if(isset($Tab[$name][$boucle])) //Le block existe déjà, on cherche la dernière boucle
                {
                    $boucle = count($Tab[$name]);
                }
            }
        }
        else //On est à la racine
        {
            if(isset($Tab[$name][$boucle])) //Le block existe déjà, on cherche la dernière boucle
            {
                $boucle = count($Tab[$name]);
            }
            
            $current = $this->CurrentBlock.$name; //on ajoute juste le nom
        }
        
        //On ajoute l'arborescence d'un block à $Tab dans le sous-Array correspondant à notre block
        //et la boucle voulu (créé si existe pas).
        $Tab[$name][$boucle] = array(
            'vars' => array(),
            'block' => array()
        );
        
        //On met à jour $this->CurrentBlock avec le block qu'on vient de créer et sa boucle.
        $this->CurrentBlock = $current.'/'.$boucle;
        if(is_array($vars)) //Si des variable on été passé, on envoi ça à $this->AddVars()
        {
            $this->AddVars($vars, true);
        }
        
        if($end != null)
        {
            for($i=0;$i<$end;$i++)
            {
                $this->remonte();
            }
        }
        
        return true; //Tout est ok.
    }
    
    /**
     * Indique la fin du fichier template.
     * Une fois appelé, le script parse le fichier template.
     * 
     * @param integer $no_echo (default: false) Indique s'il faut afficher le résultat par echo (défault) ou le renvoyer en sortie de fonction
     * 
     * @return string Retourne le résultat du parsage si $no_echo=1
     */
    public function End($no_echo=0)
    {
        $this->CurrentBlock = '/'; //On place le chemin à la racine
        $this->readFile(); //on lit le fichier et traite le fichier tpl donné
        
        if($no_echo == 0) //On affiche le résultat via un simple echo.
        {
            echo $this->TamponFinal;
            return '';
        }
        else
        {
            return $this->TamponFinal;
        }
    }
    
    /**
     * Lit le fichier template contenant l'html et le traite
     */
    protected function readFile()
    {
        //** Utilisé lorsque la lecture rencontre un block
            //Le contenu du fichier html est mit de dedans lorsqu'un 1er block est rencontrer jusqu'à sa fermeture
            $tamponBlock = '';
            
            $nameBlockFind = ''; //Le nom du block trouvé
            $nameBlockRoot = '/'; //Le chemin racine (utile quand on est dans un sous block)
            $nbOpenBlock = 0; //Le nombre de block rencontré (permet de trouvé le block fermant voulu)
        
        //**Lecture du fichier
        $fop = fopen($this->FileLink, 'r'); //Ouverture du fichier en lecture seul
        while($line = fgets($fop)) //Analise ligne par ligne
        {
            $this->analize($line, $tamponBlock, $nameBlockFind, $nbOpenBlock, $nameBlockRoot);
        }
        fclose($fop); //Fermeture du fichier
    }
    
    /**
     * Analise une ligne (d'un fichier) afin d'y trouvé les blocks ouvrant/fermant 
     * et stock en variable le contenu si on est dans un block
     * 
     * @param string $line          La ligne à lire
     * @param string $TamponBlock   (ref) La variable tampon pour le stockage de la ligne lorsqu'un block est rencontré
     * @param string $nameBlockFind (ref) Le nom du block trouvé
     * @param int    $nbOpenBlock   (ref) Le nombre de block ouvert
     * @param string $nameBlockRoot Le chemin actuel dans l'arborescence des blocks
     */
    protected function analize($line, &$TamponBlock, &$nameBlockFind, &$nbOpenBlock, $nameBlockRoot)
    {
        //Si une balise block ouvrante a été trouvé
        if($this->chercheOpenBlock($line, $nbOpenBlock) && $nbOpenBlock == 1)
        {
            $nameBlockFind = $this->recherche_NameBlock($line); //On récupère le nom du block
            
            //Gestion des <block name="a">blablabla</block>
            //Si une balise block fermante est trouvé sur la même ligne que l'ouvrante
            if($this->chercheFinBlock($line, $nbOpenBlock))
            {
                //On enlève les balises block de la ligne
                $cont = preg_replace('#<block name="'.self::REGEX.'">(.+)</block>#', '$2', $line);
                $this->traitementBlock($nameBlockRoot, $nameBlockFind, $cont); //on envoi au traitement
                
                //On vide certaines variables
                $nameBlockFind = ''; 
                $TamponBlock = '';
            }
        }
        else //Pas de block ouvrant
        {
            //Si on est dans un block (si oui la variable où est stocké son nom n'est pas vide)
            if($nameBlockFind != '')
            {
                //S'il s'agit de la fin de notre block
                if($this->chercheFinBlock($line, $nbOpenBlock))
                {
                    //On envoi au traitement
                    $this->traitementBlock($nameBlockRoot, $nameBlockFind, $TamponBlock);
                    
                    //On vide certaines variables
                    $nameBlockFind = '';
                    $TamponBlock = '';
                }
                //Sinon, on ajoute au block. On enlève le saut de ligne puis on le remet 
                //pour le cas où il était pas présent et donc éviter de l'avoir en double
                else
                {
                    $TamponBlock .= rtrim($line, PHP_EOL).PHP_EOL;
                }
            }
            //On est pas dans un block, on remplace les variables, on recherche les vues et 
            //on ajoute la ligne au tampon final qui sera affiché
            else
            {
                $this->TamponFinal .= $this->remplace_view($this->remplaceVars($line, $nameBlockRoot));
            }
        }
    }
    
    /**
     * Traite le contenu des blocks pour l'envoyer à l'analise
     * 
     * @param string $nameBlockRoot Le chemin actuel dans l'arborescence des blocks
     * @param string $nameBlockFind Le nom du block trouvé
     * @param string $contBlock     Le contenu du block
     */
    protected function traitementBlock($nameBlockRoot, $nameBlockFind, $contBlock)
    {
        $nameBlockRootTMP = $nameBlockRoot; //On garde le contenu de côté
        $tamponBlock = ''; //Le tampon qui servira si un sous block est rencontré
        
        if(in_array($nameBlockFind, $this->BanWords)) //Si le nom du block n'est pas autorisé. On stop le traitement.
        {
            return false;
        }
        
        if($nameBlockRoot != '/') //Si on est pas à la racine, on ajoute 'block' dans le chemin
        {
            $nameBlockRoot .= '/block/';
        }
        
        $nameBlockRoot .= $nameBlockFind; //On et on ajoute le nom de notre block au chemin
        $nameBlockFind = ''; //On vide la variable contenant le nom de notre block
        $nbOpenBlock = 0; //Mise à 0 du nombre de block trouvé
        
        $Tab = &$this->Block; //On positionne $Tab vers la référence de $this->Block
        $exCurrent = explode('/', $nameBlockRoot); //On découpe le chemin
        
        //Permet d'indiquer qu'il y a un eu un block non-existant durant la lecture.
        //(utile dans le cas de block pour des conditions)
        $stop = false;
        
        foreach($exCurrent as $val) //On lit chaque morceau du chemin un par un
        {
            if($val != '') //Si le nom n'est pas vide.
            {
                if(!array_key_exists($val, $Tab)) //On vérifie qu'il existe bien. Si ce n'est pas le cas ...
                {
                    $stop = true; //... On l'indique sur la variable...
                    break; //... Et on sort du foreach
                }
                
                //On position $TabVars vers la référence du sous-tableau qu'on lit par rapport à la où on est
                $Tab = &$Tab[$val];
            }
        }
        
        if($stop == false) //Si le block trouvé existe bien dans l'arborescence
        {
            //Permet de traiter le même contenu pour chaque boucle prévu par l'arborescence.
            foreach($Tab as $boucle => $infosBlock)
            {
                $nameBlockBoucle = $nameBlockRoot.'/'.$boucle; //On positionne le chemin sur la boucle
                
                //On créer un array. La découpe ce fait sur chaque fin de de ligne.
                $exEOL = explode(PHP_EOL, $contBlock);
                
                //Pour chaque ligne, on envoi à l'analize.
                foreach($exEOL as $line)
                {
                    $this->analize($line, $tamponBlock, $nameBlockFind, $nbOpenBlock, $nameBlockBoucle);
                }
            }
        }
        //Il n'existais pas dans l'arborescence, donc on affiche pas.
        //On remet la valeur de la variable à sa valeur d'origine
        else
        {
            $nameBlockRoot = $nameBlockRootTMP;
        }
    }
    
    /**
     * Permet de remplacer la balise <var /> par sa valeur
     * 
     * @param string      $line      La ligne sur laquel on doit agir
     * @param string|bool $nameBlock Le chemin dans l'arborescence où on se trouve
     * 
     * @return string La nouvelle ligne avec les balise var remplacé.
     */
    protected function remplaceVars($line, $nameBlock)
    {
        //On cherche la position de la 1ere balise <var afin de savoir s'il y en a dans la ligne.
        $posDouble = strpos($line, '<var name="');
        $posSimple = strpos($line, "<var name='");
        
        if($posDouble !== false || $posSimple !== false) //Si au moins 1 balise a été trouvé
        {
            if($nameBlock != '/' && $nameBlock != false) //Si on est dans un block.
            {
                $Tab = &$this->Block; //On positionne $Tab vers la référence de $this->Block
                $exCurrent = explode('/', $nameBlock); //On découpe le chemin
                
                //On positionne $Tab vers une référence vers le sous-array que l'on souhaite
                foreach($exCurrent as $val)
                {
                    if($val != '')
                    {
                        $Tab = &$Tab[$val];
                    }
                }
                
                //Puis on place $TabVars vers la référence du sous-array 'vars' par rapport à la où on est.
                $Tab = &$Tab['vars'];
            }
            else //On n'est pas dans un block, $TabVars prend la référence de $this->Root_Variable
            {
                $Tab = $this->Root_Variable;
            }
            
            do
            {
                $nameVar = array(); //Déclaration de variable à array vide.

                //On recherche dans la ligne la 1ere balise <var name"" />.
                //Le contenu de name est mis dans $nameVar[1]
                //Si la balise a été trouvé, $search vaux true, sinon false.
                $search_double = preg_match('#<var name="'.self::REGEX.'" />#', $line, $nameVar);
                $search_simple = false;
                
                //Si une balise a été trouve. On remplace la balise par son contenu. 
                //On le fait que pour une seul balise à la fois
                if($search_double)
                {
                    if(isset($Tab[$nameVar[1]]))
                    {
                        $line = preg_replace('#<var name="'.self::REGEX.'" />#', $Tab[$nameVar[1]], $line, 1);
                    }
                    else
                    {
                        if(isset($this->Gen_Variable[$nameVar[1]]))
                        {
                            $line = preg_replace(
                                            '#<var name="'.self::REGEX.'" />#', 
                                            $this->Gen_Variable[$nameVar[1]], 
                                            $line, 
                                            1
                            );
                        }
                        else
                        {
                            echo 'Template Erreur : Variable '.$nameVar[1].' inconnue.<br/>';
                            exit;
                        }
                    }
                }
                else
                {
                    $search_simple = preg_match("#<var name=\'".self::REGEX."\' />#", $line, $nameVar);
                    if($search_simple)
                    {
                        if(isset($Tab[$nameVar[1]]))
                        {
                            $line = preg_replace("#<var name='".self::REGEX."' />#", $Tab[$nameVar[1]], $line, 1);
                        }
                        else
                        {
                            if(isset($this->Gen_Variable[$nameVar[1]]))
                            {
                                $line = preg_replace(
                                                "#<var name='".self::REGEX."' />#", 
                                                $this->Gen_Variable[$nameVar[1]],
                                                $line,
                                                1
                                );
                            }
                            else
                            {
                                echo 'Template Erreur : Variable '.$nameVar[1].' inconnue.<br/>';
                                exit;
                            }
                        }
                    }
                }
                
                if($search_simple || $search_double)
                {
                    $search = true;
                }
                else
                {
                    $search = false;
                }
            }
            while($search); //On répete tant qu'il reste des balises <var /> dans la ligne
        }

        $posVarUri = strpos($line, '<varUri');
        if($posVarUri !== false)
        {
            global $base_url;
            
            $search = false;
            do
            {
                $nameVar = array(); //Déclaration de variable à array vide.
                
                //On recherche dans la ligne la 1ere balise <var name"" />.
                //Le contenu de name est mis dans $nameVar[1]
                //Si la balise a été trouvé, $search vaux true, sinon false.
                $search_uri1 = preg_match('#<varUri />#', $line, $nameVar);
                
                if($search_uri1)
                {
                    $line = preg_replace('#<varUri />#', $base_url, $line, 1);
                }
                else
                {
                    $search_uri2 = preg_match('#<varUri/>#', $line, $nameVar);
                    if($search_uri2)
                    {
                        $line = preg_replace('#<varUri/>#', $base_url, $line, 1);
                    }
                }
            }
            while($search);
        }
        
        return $line; //Puis on retourne la ligne avec les balises <var /> remplacé par leurs valeurs respectives
    }
    
    /**
     * Permet de savoir si un block est présent dans la ligne et si oui, son nom.
     * 
     * @param string $line La ligne où l'on doit chercher
     * 
     * @return null|string Le nom du block s'il y en a un de présent. Sinon renvoi null
     */
    protected function recherche_NameBlock($line)
    {
        $pos = strpos($line, '<block name="'); //On recherche s'il y a une balise block ouvrante
        
        if($pos !== false) //Si c'est le cas
        {
            preg_match('#<block name="'.self::REGEX.'">#', $line, $nameBlock); //On récupère le nom dans $nameBlock[1]
            return $nameBlock[1]; //On retourne le nom du block
        }
        else //Sinon on renvoi null
        {
            return null;
        }
    }
    
    /**
     * Permet de savoir s'il y a une balise block ouvrante dans la ligne et met à jour le nombre de block trouvé
     * 
     * @param string $line   La ligne dans laquelle on doit chercher
     * @param int    $nbOpen (ref) Le nombre de block ouvert trouvé
     * 
     * @return bool True si une balise block ouvrante est trouvé. False sinon
     */
    protected function chercheOpenBlock($line, &$nbOpen)
    {
        $pos = strpos($line, '<block name="'); //On recherche s'il y a une balise block ouvrante
        
        if($pos !== false) //Si c'est le cas
        {
            $nbOpen += 1; //Incrémentation du nombre de balise block trouvé
            return true; //Retourne true
        }
        else //Sinon retourne false
        {
            return false;
        }
    }
    
    /**
     * Permet de savoir si on est sur la dernière balise block fermante dans la ligne
     * Et met à jour le nombre de block trouvé pour chaque balise block fermante trouvée.
     * 
     * @param string $line   La ligne dans laquelle on doit chercher
     * @param int    $nbOpen (ref) Le nombre de block ouvert trouvé
     * 
     * @return bool True si la dernière balise block fermante est trouvé. False sinon
     */
    protected function chercheFinBlock($line, &$nbOpen)
    {
        $pos = strpos($line, '</block>'); //On recherche s'il y a une balise block fermante
            
        if($pos !== false) //Si c'est le cas
        {
            $nbOpen -= 1; //Décrémentation du nombre de balise block trouvé
            if($nbOpen == 0) //Si on est à la dernière balise block, on retourne true
            {
                return true;
            }
            else //Sinon false
            {
                return false;
            }
        }
        else //Pas de balise block fermante, on retourne false.
        {
            return false;
        }
    }
    
    /**
     * Recherche et remplace les block <view> par leurs équivalents
     * 
     * @param string $line la ligne dans laquelle on doit chercher
     * 
     * @return string Le résultat après traitement de la vue
     */
    protected function remplace_view($line)
    {
        //Recherche
        $pos = strpos($line, '<view dir="');
        
        if($pos !== false)
        {
            $search  = '#<view '.self::REGEXATTR.'="'.self::REGEX.'" ';
            $search .= self::REGEXATTR.'="'.self::REGEX.'" ';
            $search .= self::REGEXATTR.'="'.self::REGEXJSON.'" (';
            $search .= self::REGEXATTR.'="'.self::REGEX.'") />#';
            
            //Récupération des infos
            $Var = array();
            $match = preg_match($search, $line, $Var);
            
            foreach($Var as $key => $val)
            {
                $key2 = $key + 1;
                if($key < 6 && $key > 0)
                {
                    if($key%2 == 1)
                    {
                        ${$val} = $Var[$key2];
                    }
                }
            }
            
            $opt .= $Var[7].$Var[8];
            if(isset($Var[9]))
            {
                $mods = $Var[11];
            }
            else
            {
                $mods = false;
            }
            
            //Remplacement
            $line = preg_replace($search, '', $line);
            
            //Inclusion de la vue
            global $path;
            $link = $path;
            
            if($mods != 'false')
            {
                $link = '../modules/';
                if($mods != 'true')
                {
                    $link .= $mods.'/';
                }
            }
            
            $link .= $dir.'/'.$file.'.php';
            require_once($link);
            
            return $TamponFinal;
        }
        else
        {
            return $line;
        }
    }
}