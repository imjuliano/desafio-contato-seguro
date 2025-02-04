<?php

namespace Contatoseguro\TesteBackend\Controller;

use Contatoseguro\TesteBackend\Service\CompanyService;
use Contatoseguro\TesteBackend\Service\ProductService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class ReportController
{
    private ProductService $productService;
    private CompanyService $companyService;
    
    public function __construct()
    {
        $this->productService = new ProductService();
        $this->companyService = new CompanyService();
    }
    
    public function generate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $adminUserId = $request->getHeader('admin_user_id')[0];
        
        $data = [];
        $data[] = [
            'Id do produto',
            'Nome da Empresa',
            'Nome do Produto',
            'Valor do Produto',
            'Categorias do Produto',
            'Data de Criação',
            'Logs de Alterações'
        ];
        
        $products = $this->productService->getAll($adminUserId);
    
        foreach ($products as $i => $product) {
            $stm = $this->companyService->getNameById($product->company_id);
            
            if (is_array($stm)) {
                $companyName = $stm['name'];
            } else {
                $companyName = $stm->fetch()->name;
            }
        
            $stm = $this->productService->getLog($product->id);
            $productLogs = $stm->fetchAll();
        
            $data[$i+1][] = $product->id;
            $data[$i+1][] = $companyName;
            $data[$i+1][] = $product->title;
            $data[$i+1][] = $product->price;
            $data[$i+1][] = implode(', ', $product->category);
            $data[$i+1][] = isset($product->createdAt) ? $product->createdAt : 'N/A';
        
            $logText = '';
            if (empty($productLogs)) {
                $logText = '(Desconhecido, Ação desconhecida, Sem informações)'; 
            } else {
                foreach ($productLogs as $log) {
                    $userName = isset($log->user_name) ? ucfirst($log->user_name) : 'Desconhecido';
                    $action = strtolower($log->action);
                    $logDate = isset($log->timestamp) ? date('d/m/Y H:i:s', strtotime($log->timestamp)) : 'N/A';
        
                    switch ($action) {
                        case 'create':
                            $action = 'Criação';
                            break;
                        case 'update':
                            $action = 'Atualização';
                            break;
                        case 'delete':
                            $action = 'Remoção';
                            break;
                        default:
                            $action = 'Ação Desconhecida';
                            break;
                    }
        
                    $logText .= "({$userName}, {$action}, {$logDate}), ";
                }
            }
        
            $logText = rtrim($logText, ', ');
        
            $data[$i+1][] = $logText;
        }      
        
        
        
        $report = "<table style='font-size: 10px; border: 1px solid #ddd; border-collapse: collapse;'>";
        foreach ($data as $row) {
            $report .= "<tr>";
            foreach ($row as $column) {
                $report .= "<td style='border: 1px solid #ddd; padding: 5px;'>{$column}</td>";
            }
            $report .= "</tr>";
        }
        $report .= "</table>";
        
        $response->getBody()->write($report);
        return $response->withStatus(200)->withHeader('Content-Type', 'text/html');
    }

    public function generateForProduct(Request $request, Response $response, $args)
    {
        $productId = (int) $args['id']; 
    
        $product = $this->productService->getOne($productId);
        $lastPriceChange = $this->productService->getLastPriceChange($productId); 
    
        $lastPriceLog = $lastPriceChange ?
            "Última alteração de preço: {$lastPriceChange->user_name}, {$lastPriceChange->timestamp}" :
            "Nenhuma alteração de preço registrada.";
    
        $responseData = [
            'product_id' => $productId,
            'product_name' => $product['title'],
            'last_price_change' => $lastPriceLog 
        ];
    
        $response->getBody()->write(json_encode($responseData));
    
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    

    public function getProductLog($request, $response, $args)
{
    $productId = $args['id'];
    $productService = new ProductService();
    $logs = $productService->getLog($productId);

    return $response->withJson($logs);
}

    
}
