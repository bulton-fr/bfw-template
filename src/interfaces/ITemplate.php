<?php
/**
 * Interfaces en rapport avec le système de template
 * @author Vermeulen Maxime <bulton.fr@gmail.com>
 * @version 1.0
 */

namespace BFWTplInterface;

/**
 * Interface de la classe template
 * @package bfw-template
 */
interface ITemplate
{
    const REGEX = '([0-9a-zA-Z._-]+)'; //La regex pour la recherche dans les blocks et variables
    const REGEXJSON = '(\[|\{|\")(.*)(\"|\}|\])'; //La regex pour la recherche prévu pour marcher avec json
    const REGEXATTR = '(dir|file|opt|mod)'; //La regex pour les noms des attributs
    
    /**
     * Accesseur get vers l'attribut $Block
     * 
     * @return array
     */
    public function getBlock();
    
    /**
     * A indiquer à la fin de l'utilisation du 1er block.
     * Permet de revenir au chemin racine dans l'arborescence pour les blocks suivant,
     * de façon à ce qu'il ne soit pas mis comme un sous-block du dernier block ouvert
     * @return void
     */
    public function EndBlock();
    
    /**
     * Permet d'ajouter une variable à une liste qui sera lu partout, qu'on soit dans un block ou non
     * 
     * @param array $vars Les variables à ajouter (nom => valeur)
     * @return void
     */
    public function AddGeneralVars($vars);
    
    /**
     * Ajoute des variables à un block ou non
     * 
     * @param array       $vars Les variables à ajouter (nom => valeur)
     * @param bool|string $name (default: false) Indique si c'est pour un block (le block courant est utilisé)
     * Il est aussi possible de donner le nom du block, cependant il est préférable de
     * le faire sur des block qui sont des conditions et non des blocks boucle.
     * @return false|null
     */
    public function AddVars($vars, $name=false);
    
    /**
     * Permet de remonter dans les blocks
     * @return void
     */
    public function remonte();
    
    /**
     * Ajoute un sous block au système et appelle méthode EndBlock() à la fin
     * 
     * @param string    $name      Le nom du block
     * @param array|int $varsOrEnd (default: null) Les variables du block à passer (nom => valeur). Si int voir 3eme paramètre
     * @param int       $end       (default: null) Indique de combien de block on doit remonter
     * @return void
     */
    public function AddBlockWithEnd($name, $varsOrEnd = null, $end=null);
    
    /**
     * Ajoute un sous block au système
     * 
     * @param string $name Le nom du block
     * @param array  $vars (default: null) Les variables du block à passer (nom => valeur)
     * @param int    $end  (default: null) Indique de combien de block on doit remonter
     * 
     * @return bool Retourne true si tout c'est bien passé, False si le nom du block n'est pas autorisé.
     */
    public function AddBlock($name, $vars = null, $end=null);
    
    /**
     * Indique la fin du fichier template.
     * Une fois appelé, le script parse le fichier template.
     * 
     * @param integer $no_echo (default: false) Indique s'il faut afficher le résultat par echo (défault) ou le renvoyer en sortie de fonction
     * 
     * @return string Retourne le résultat du parsage si $no_echo=1
     */
    public function End($no_echo=0);
}
?>