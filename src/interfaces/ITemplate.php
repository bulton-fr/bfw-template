<?php

namespace BFWTplInterface;

interface ITemplate
{
    const REGEX = '([0-9a-zA-Z._-]+)'; //La regex pour la recherche dans les blocks et variables
    const REGEXJSON = '(\[|\{|\")(.*)(\"|\}|\])'; //La regex pour la recherche prévu pour marcher avec json
    const REGEXATTR = '(dir|file|opt|mod)'; //La regex pour les noms des attributs
    
    /**
     * Accesseur get vers l'attribut $Block
     */
    public function getBlock();
    
    /**
     * Construteur
     * @param string : Le lien vers le fichier tpl
     * @param array [opt] : Des variables n'étant pas dans un block à passer (nom => valeur)
     */
    public function __construct($file, $vars=null);
    
    /**
     * A indiquer à la fin de l'utilisation du 1er block.
     * Permet de revenir au chemin racine dans l'arborescence pour les blocks suivant,
     * de façon à ce qu'il ne soit pas mis comme un sous-block du dernier block ouvert
     */
    public function EndBlock();
    
    /**
     * Permet d'ajouter une variable à une liste qui sera lu partout, qu'on soit dans un block ou non
     * @param array : Les variables à ajouter (nom => valeur)
     * @return bool [opt] : Uniquement si une erreur survient. Ne retourne rien si tout se passe bien.
     */
    public function AddGeneralVars($vars);
    
    /**
     * Ajoute des variables à un block ou non
     * @param array : Les variables à ajouter (nom => valeur)
     * @param bool/string [opt] : Indique si c'est pour un block (le block courant est utilisé)
     *                            Il est aussi possible de donner le nom du block, cependant il est préférable de
     *                            le faire sur des block qui sont des conditions et non des blocks boucle.
     * @return bool [opt] : Uniquement si une erreur survient. Ne retourne rien si tout se passe bien.
     */
    public function AddVars($vars, $name=false);
    
    /**
     * Permet de remonter dans les blocks
     */
    public function remonte();
    
    /**
     * Ajoute un sous block au système et appelle méthode EndBlock() à la fin
     * @param string : Le nom du block
     * @param array/int [opt] : Les variables du block à passer (nom => valeur). Si int voir 3eme paramètre
     * @param int [opt] : Indique de combien de block on doit remonter
     * @return bool : Retourne true si tout c'est bien passé, False si le nom du block n'est pas autorisé.
     */
    public function AddBlockWithEnd($name, $varsOrEnd = null, $end=null);
    
    /**
     * Ajoute un sous block au système
     * @param string : Le nom du block
     * @param array [opt] : Les variables du block à passer (nom => valeur)
     * @param int [opt] : Indique de combien de block on doit remonter
     * @return bool : Retourne true si tout c'est bien passé, False si le nom du block n'est pas autorisé.
     */
    public function AddBlock($name, $vars = null, $end=null);
    
    /**
     * Indique la fin du fichier template.
     * Une fois appelé, le script parse le fichier template.
     * @param bool $no_echo [opt] : Indique s'il faut afficher le résultat par echo (défault)
     *                               ou le renvoyer en sortie de fonction
     * @return string : Si $no_echo = 1 Alors ça retourne le résultat du parsage
     */
    public function End($no_echo=0);
}
?>