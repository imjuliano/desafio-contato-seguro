<?php

namespace Contatoseguro\TesteBackend\Service;

use PDO;
use Contatoseguro\TesteBackend\Config\DB;

class CompanyService
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = DB::connect();
    }

    public function getNameById($id)
    {
        if ($id === NULL) {
            return null; 
        }
    
        $stm = $this->pdo->prepare("SELECT name FROM company WHERE id = :id");
        $stm->bindParam(':id', $id, \PDO::PARAM_INT); 
        $stm->execute();
        
        return $stm->fetch(\PDO::FETCH_ASSOC);
    }
    
}
