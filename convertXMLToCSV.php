<?php

class convertXMLToCSV
{
    /**
     * Path to the xsd file
     * @var string
     */
    private $xsdPath = "simpleFamily.xsd";
    /**
     * doc 
     * @var DOMDocument
     */
    private $doc = null;
    /**
     * Array of error string
     * @var array
     */
    private $errors = array();
    /**
     * Temporary array to stock CSV row before export
     * @var array
     */
    private $csvArray = array();
    
    /**
     * Constructor
     * 
     * Take the XML path and create the csvArray
     * 
     * @param string $xmlContent path to the XML file
     */
    public function __construct($xmlPath, $xsdPath = false)
    {
        if ($xsdPath) {
            $this->xsdPath = $xsdPath;
        }
        if ($this->validateXMLFile($xmlPath)) {
            $this->parseFamily();
        } else {
            throw new Exception(array_pop($this->errors));
        }
    }
    /**
     * Export the CSV array in a CSV file
     * 
     * @param string $path path where the CSV file will be unloaded
     * @param string $encoding default value UTF8
     * @param string $delimiter default value ;
     * @param string $enclosure default value "
     */
    public function exportToCSV($path, $encoding = "utf8", $delimiter = ";", $enclosure = '"')
    {
        $fp = fopen($path, 'w');
        
        foreach ( $this->csvArray as $currentLine ) {
            if ($encoding != "utf8") {
                $currentLine = $this->convertLine($currentLine, $encoding);
            }
            fputcsv($fp, $currentLine, $delimiter, $enclosure);
        }
        
        fclose($fp);
    }
    /**
     * Analyze the family
     */
    protected function parseFamily()
    {
        $headerLine = array();
        
        $headerLine[0] = "BEGIN";
        
        $familyTag = $this->doc->getElementsByTagName("family")->item(0);
        
        $headerLine[1] = $familyTag->attributes->getNamedItem("fatherFamily") ? $familyTag->attributes->getNamedItem("fatherFamily")->textContent : "";
        $headerLine[2] = $familyTag->attributes->getNamedItem("title") ? $familyTag->attributes->getNamedItem("title")->textContent : "";
        $headerLine[3] = "";
        $headerLine[4] = "";
        $headerLine[5] = $familyTag->attributes->getNamedItem("name")->textContent;
        
        $this->csvArray[] = array(
            "//",
            "famille mère",
            "Titre",
            "Id",
            "Classe",
            "Nom Logique"
        );
        $this->csvArray[] = $headerLine;
        
        foreach ( $familyTag->childNodes as $currentChild ) {
            /*@var $currentChild DOMElement */
            if ($currentChild->nodeName == "properties") {
                foreach ( $currentChild->childNodes as $currentProperty ) {
                    /*@var $currentProperty DOMElement */
                    if ($currentProperty->nodeName == "property") {
                        $currentOption = $this->analyzeOption($currentProperty);
                        $this->csvArray[] = $this->analyzeOption($currentProperty);
                    }
                }
                break;
            }
        }
        $this->csvArray[] = array(
            "//Attributes",
            "id attribut",
            "id attribut parent",
            "libelle",
            "titre?",
            "résumé",
            "type",
            "ordre",
            "visibilité",
            "obligatoire?",
            "lien",
            "php-file",
            "php-function",
            "external-link",
            "contraintes",
            "options"
        );
        $this->parseFamilyAttributes();
        $this->csvArray[] = array(
            "//Parameters",
            "id attribut",
            "id attribut parent",
            "libelle",
            "titre?",
            "résumé",
            "type",
            "ordre",
            "visibilité",
            "obligatoire?",
            "lien",
            "php-file",
            "php-function",
            "external-link",
            "contraintes",
            "options"
        );
        $this->parseFamilyParameters();
        $this->csvArray[] = array(
            "END"
        );
    }
    /**
     * Parse the attributes of the family
     */
    protected function parseFamilyAttributes()
    {
        $attributes = $this->doc->getElementsByTagName("attributes")->item(0);
        if ($attributes) {
            foreach ( $attributes->childNodes as $currentChild ) {
                if ($currentChild->nodeName == "attribute") {
                    $this->parseFamilyElement("attribute", "ATTR", $currentChild);
                }
            }
        }
    }
    /**
     * Parse the parameters of the family
     */
    protected function parseFamilyParameters()
    {
        $parameters = $this->doc->getElementsByTagName("parameters")->item(0);
        if ($parameters) {
            foreach ( $parameters->childNodes as $currentChild ) {
                if ($currentChild->nodeName == "parameter") {
                    $this->parseFamilyElement("parameter", "PARAM", $currentChild);
                }
            }
        }
    }
    /**
     * Parse an element and the children of the element
     * 
     * @param string $currentNodeType attribute or parameter
     * @param string $currentPrefixe current prefixe
     * @param DOMElement $elementRoot the current node element
     * @param string $fatherName father name (used in recursion mode)
     */
    protected function parseFamilyElement($currentNodeType, $currentPrefixe, DOMElement $elementRoot, $fatherName = "")
    {
        $currentLine = array();
        $currentProperties = array();
        $childrenNode = false;
        $currentLine[0] = $elementRoot->attributes->getNamedItem("isHeritated") && ($elementRoot->attributes->getNamedItem("isHeritated")->textContent == "true") ? "MOD" . $currentPrefixe : $currentPrefixe;
        $currentLine[1] = $elementRoot->attributes->getNamedItem("name")->textContent;
        $currentLine[2] = $fatherName;
        $currentLine[6] = $elementRoot->attributes->getNamedItem("type") ? $elementRoot->attributes->getNamedItem("type")->textContent : "";
        $currentLine[7] = $elementRoot->attributes->getNamedItem("ord") ? $elementRoot->attributes->getNamedItem("ord")->textContent : "";
        $currentLine[8] = $elementRoot->attributes->getNamedItem("visibility") ? $elementRoot->attributes->getNamedItem("visibility")->textContent : "";
        foreach ( $elementRoot->childNodes as $currentChild ) {
            /*@var $currentChild DOMElement */
            if ($currentChild->nodeName == "properties") {
                /*@var $currentProperty DOMElement */
                foreach ( $currentChild->childNodes as $currentProperty ) {
                    /*@var $currentProperty DOMElement */
                    if ($currentProperty->nodeType == 1) {
                        if ($currentProperty->nodeName != "options") {
                            $currentProperties[$currentProperty->nodeName] = $currentProperty->textContent;
                        } else {
                            $options = "";
                            foreach ( $currentProperty->childNodes as $currentOption ) {
                                /*@var $currentChild DOMElement */
                                if ($currentOption->nodeName == "option") {
                                    $options .= implode("|", $this->analyzeOption($currentOption)) . ",";
                                }
                            }
                            $options = substr_replace($options, "", -1);
                            $currentProperties["options"] = $options;
                        }
                    }
                }
            } elseif ($currentChild->nodeName == "children") {
                $childrenNode = $currentChild;
            }
        }
        $currentLine[3] = isset($currentProperties["label"]) ? $currentProperties["label"] : "";
        $currentLine[4] = isset($currentProperties["inTitle"]) && ($currentProperties["inTitle"] == "true") ? "Y" : "N";
        $currentLine[5] = isset($currentProperties["inAbstract"]) && ($currentProperties["inAbstract"] == "true") ? "Y" : "N";
        $currentLine[9] = isset($currentProperties["needed"]) && ($currentProperties["needed"] == "true") ? "Y" : "N";
        $currentLine[10] = isset($currentProperties["link"]) ? $currentProperties["link"] : "";
        $currentLine[11] = isset($currentProperties["phpFile"]) ? $currentProperties["phpFile"] : "";
        $currentLine[12] = isset($currentProperties["phpFunc"]) ? $currentProperties["phpFunc"] : "";
        $currentLine[13] = isset($currentProperties["elink"]) ? $currentProperties["elink"] : "";
        $currentLine[14] = isset($currentProperties["constraint"]) ? $currentProperties["constraint"] : "";
        $currentLine[15] = isset($currentProperties["options"]) ? $currentProperties["options"] : "";
        
        if (isset($currentProperties["typeModifier"]) && isset($currentLine[6])) {
            $currentLine[6] = sprintf('%s("%s")', $currentLine[6], $currentProperties["typeModifier"]);
        }
        
        ksort($currentLine);
        
        $this->csvArray[] = $currentLine;
        
        if (isset($currentProperties["defaultValue"])) {
            $this->csvArray[] = array(
                "DEFAULT",
                $currentLine[1],
                $currentProperties["defaultValue"]
            );
        }
        if ($childrenNode) {
            foreach ( $childrenNode->childNodes as $currentChild ) {
                if ($currentChild->nodeName == $currentNodeType) {
                    $this->parseFamilyElement($currentNodeType, $currentPrefixe, $currentChild, $currentLine[1]);
                }
            }
        }
    }
    /**
     * Analyze an option node
     * 
     * @param DOMElement $node current Node element
     * 
     * @return array key, value
     */
    private function analyzeOption(DOMElement $node)
    {
        return array(
            $node->attributes->getNamedItem("name")->textContent,
            $node->textContent
        );
    }
    /**
     * validate an XML file against an XSD
     * 
     * @param string $xml path
     * 
     * @ return boolean
     */
    protected function validateXMLFile($xml)
    {
        libxml_use_internal_errors(true);
        
        $this->doc = new DOMDocument();
        $this->doc->load($xml);
        
        $validation = $this->doc->schemaValidate($this->xsdPath);
        
        if ($validation) {
            return true;
        } else {
            $stringReturn = "";
            $errors = libxml_get_errors();
            foreach ( $errors as $error ) {
                $stringReturn .= "\n";
                switch ($error->level) {
                case LIBXML_ERR_WARNING :
                    $stringReturn .= "Warning $error->code: ";
                    break;
                case LIBXML_ERR_ERROR :
                    $stringReturn .= "Error $error->code: ";
                    break;
                case LIBXML_ERR_FATAL :
                    $stringReturn .= "Fatal Error $error->code: ";
                    break;
                }
                $stringReturn .= trim($error->message);
                if ($error->file) {
                    $stringReturn .= " in $error->file";
                }
                $stringReturn .= " on line $error->line\n";
            }
            libxml_clear_errors();
        }
        $this->errors[] = $stringReturn;
        return false;
    }
    /**
     * Convert an array in one encoding in another
     * 
     * @param array $currentLine line to analyze
     * @param string $encoding encoding
     * 
     * @return array converted array
     */
    private function convertLine($currentLine, $encoding)
    {
        $returnArray = array();
        foreach ( $currentLine as $currentValue ) {
            $returnArray[] = iconv("utf8", $encoding, $currentValue);
        }
        return $returnArray;
    }

}

?>