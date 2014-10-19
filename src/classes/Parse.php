<?php
/**
 * Classes en rapport avec le système de template
 * @author Vermeulen Maxime <bulton.fr@gmail.com>
 * @version 1.0
 */

namespace BFWTpl;

/**
 * Système de parsage
 * @package bfw-template
 */
class Parse
{
    /**
     * @constant string REGEX : La regex pour la recherche dans les blocks et variables
     */
    const REGEX = '([0-9a-zA-Z._-]+)';
    
    /**
     * @constant string REGEXJSON : La regex pour la recherche prévu pour marcher avec json
     */
    const REGEXJSON = '(\[|\{|\")(.*)(\"|\}|\])';
    
    /**
     * @constant string REGEXATTR : La regex pour les noms des attributs
     */
    const REGEXATTR = '(dir|file|opt|mod)';
    
    /**
     * @var $_kernel L'instance du Kernel
     */
    protected $_kernel;
    
    /**
     * @var $Template L'instance de la classe Template
     */
    protected $Template;
    
    /**
     * Constructeur
     * 
     * @param \BFWTpl\Template $tpl : L'instance de la classe Template utilisé
     */
    public function __construc(Template $tpl)
    {
        $this->_kernel = getKernel();
        $this->Template = $tpl;
    }
    
    /**
     * Lance le parsage
     * 
     * @param integer $no_echo (default: 0) Indique s'il faut afficher le résultat par echo (défault) ou le renvoyer en sortie de fonction
     * 
     * @return string Retourne le résultat du parsage si $no_echo=1
     */
    public function run($no_echo=0)
    {
        $this->readFile(); //on lit le fichier et traite le fichier tpl donné
        
        if($no_echo == 0) //On affiche le résultat via un simple echo.
        {
            echo $tpl->getTamponFinal();
            return '';
        }
        else
        {
            return $tpl->getTamponFinal();
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
                $cont = preg_replace('#<block name="'.$this->Template->REGEX.'">(.+)</block>#', '$2', $line);
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
                $this->TamponFinal .= $this->remplace_view($this->remplaceAttributs($line, $nameBlockRoot));
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
        $tamponBlock      = ''; //Le tampon qui servira si un sous block est rencontré
        
        //Si le nom du block n'est pas autorisé. On stop le traitement.
        if(in_array($nameBlockFind, $this->BanWords)) {return false;}
        
        //Si on est pas à la racine, on ajoute 'block' dans le chemin
        if($nameBlockRoot != '/') {$nameBlockRoot .= '/block/';}
        
        $nameBlockRoot .= $nameBlockFind; //On et on ajoute le nom de notre block au chemin
        $nameBlockFind = ''; //On vide la variable contenant le nom de notre block
        $nbOpenBlock   = 0; //Mise à 0 du nombre de block trouvé
        
        $Tab = &$this->getBlock(); //On positionne $Tab vers la référence de $this->Block
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
    protected function remplaceAttributs($line, $nameBlock)
    {
        $this->remplaceAttributsVar($line, $nameBlock);
        $this->remplaceAttributsVarUri($line);
        
        return $line; //Puis on retourne la ligne avec les balises <var /> remplacé par leurs valeurs respectives
    }
    
    /**
     * Remplace les balise html <var name... par la valeur de la variable
     * 
     * @param string      $line      La ligne sur laquel on doit agir
     * @param string|bool $nameBlock Le chemin dans l'arborescence où on se trouve
     * 
     * @return void
     */
    protected function remplaceAttributsVar(&$line, $nameBlock)
    {
        //On cherche la position de la 1ere balise <var afin de savoir s'il y en a dans la ligne.
        $posDouble = strpos($line, '<var name="');
        $posSimple = strpos($line, "<var name='");
        
        //Si la balise n'a pas été trouvé, on sort.
        if(!($posDouble !== false || $posSimple !== false)) {return;}
        
        //Récupère les variables
        $Tab = $this->getVars($nameBlock);
        
        do
        {
            //Initialisation
            $nameVarSimple = array();
            $nameVarDouble = array();
            $search        = false;
            
            //On recherche dans la ligne la 1ere balise <var...
            //Le contenu de name est mis dans $nameVar[1]
            //Si la balise a été trouvé, $search vaux true, sinon false.
            $search_double = preg_match('#<var name="'.self::REGEX.'" />#', $line, $nameVarDouble);
            $search_simple = preg_match("#<var name=\'".self::REGEX."\' />#", $line, $nameVarSimple);
            
            //Si la balise à double quote a été trouvé
            if(!$search_double && !$search_simple) {break;}
            
            //Initialise avec les valeurs double quote.
            $replace = '#<var name="'.self::REGEX.'" />#';
            $nameVar = $nameVarDouble;
            
            //Si la balise à simple quote à été trouvé
            if($search_simple)
            {
                //Regex pour le simple quote et update de $nameVar pour utilisé le bon array
                $replace = "#<var name='".self::REGEX."' />#";
                $nameVar = $nameVarSimple;
            }
            
            //On passe $search à true car la variable a été trouvé.
            $search = true;
            
            //Recherche de l'attribut name dans les variable connu par le système
            $varValue = '';
            
            //Récupère les variables générales
            $genVariable = $this->getGen_Variable();
            
            //Recherche sur les variables locals aux template
            if(isset($Tab[$nameVar[1]])) {$varValue = $Tab[$nameVar[1]];}
            
            //Si la balise a été trouvé dans les variables globaux
            elseif(isset($genVariable[$nameVar[1]]))
            {
                $varValue = $genVariable[$nameVar[1]];
            }
            
            //Erreur si la balise n'a pas été trouvé
            else
            {
                echo 'Template Erreur : Variable '.$nameVar[1].' inconnue.<br/>';
                exit;
            }
            
            //Remplace dans la ligne la balise par la valeur
            $line = preg_replace($replace, $varValue, $line, 1);
        }
        //On répete tant qu'il reste des balises <var /> dans la ligne
        while($search);
    }
    
    /**
     * Remplace les balise html <varUri... par la valeur du base_url
     * 
     * @param string $line La ligne sur laquel on doit agir
     * 
     * @return void
     */
    protected function remplaceAttributsVarUri(&$line)
    {
        //Recherche de la balise "<var Uri />"
        $posVarUri = strpos($line, '<varUri');
        if($posVarUri === false) {return;}
        
        //Initialisation
        global $base_url;
        $search = false;
        $regex  = '#<varUri(\s*)/>#';
        
        do
        {
            //Déclaration de variable à array vide.
            $nameVar = array();
            
            //On recherche dans la ligne la 1ere balise <varUri...
            $search_uri1 = preg_match($regex, $line, $nameVar);
            
            //Si la balise a été trouvé
            if($search_uri1)
            {
                //On remplace dans la ligne la balise par la valeur
                $line   = preg_replace($regex, $base_url, $line, 1);
                
                //Met à jour $search
                $search = true;
            }
        }
        while($search);
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
        else {return false;}
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
            
            //Si on est à la dernière balise block, on retourne true
            if($nbOpen == 0) {return true;}
            else {return false;}
        }
        //Pas de balise block fermante, on retourne false.
        else {return false;}
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
        $pos = strpos($line, '<view dir="'); //Recherche
        if($pos === false) {return $line;} //Si balise pas trouvé, on sort
        
        //Création de la regex pour supporter la balise.
        //<view dir="mydir" file="myfile" opt="mesoptionsJson" mod="nomDuModule" />
        $search  = '#<view '.self::REGEXATTR.'="'.self::REGEX.'" ';
        $search .= self::REGEXATTR.'="'.self::REGEX.'" ';
        $search .= self::REGEXATTR.'="'.self::REGEXJSON.'" (';
        $search .= self::REGEXATTR.'="'.self::REGEX.'") />#';
        
        //Initialise les variables
        $dir         = ''; //Le dossier à lire
        $file        = ''; //Le fichier à inclure
        $opt         = ''; //Les différetes options
        $TamponFinal = ''; //La variable qui contiendra la sortie du tpl du controller inclu
        $Var         = array(); //Tableau contenant chaque élément trouvé dans le preg_match
        
        global $path;
        $link = $path; //Initialisation des liens pour inclure la vue
        
        $match = preg_match($search, $line, $Var); //Récupération des infos
        
        if(!$match) //Si la balise existe mais la regex à planter.
        {
            echo 'Template Erreur : La balise view n\'a pas pu être traité.<br/>Balise : '.htmlentities($line).'<br/>';
            exit;
        }
        
        $authorizedAttr = array('dir', 'file', 'opt', 'mod'); //liste des attributs à lire
        $keyAttr        = array(1, 3, 5); //Emplacement possible de chaque attribut en sortie du preg_match
        
        foreach($keyAttr as $key) //On lit ces clé possible
        {
            //On vérifie que la clé est bien autorisé
            if(!in_array($Var[$key], $authorizedAttr)) {continue;}
            
            //Si c'est ok, on met à jour la variable correspondante avec la valeur de l'attribut de la balise
            ${$Var[$key]} = $Var[$key+1];
        }
        
        //Gestion des modules
        if(isset($Var[7])) {$link = '../modules/'.$Var[8].'/';}
        
        if($file == '') //Si aucun fichier est indiqué, on fait une erreur
        {
            echo 'Template Erreur : Le controleur à inclure n\'a pas été trouvé.<br/>Balise : '.htmlentities($line).'<br/>';
            exit;
        }
        
        $link .= $dir.'/'.$file.'.php'; //Maj du lien à inclure avec le dossier et le fichier
        require_once($link); //On inclus le fichier
        
        //Et on retourne la variable qui est sensé contenir le tpl du controller.
        return $TamponFinal;
    }
}
