<?php
namespace Contatoseguro\TesteBackend\Service;

use PDO;
use Contatoseguro\TesteBackend\Config\DB;
use Contatoseguro\TesteBackend\Model\Product;

class ProductService
{
    private \PDO $pdo;
    public function __construct()
    {
        $this->pdo = DB::connect();
    }

    public function getAll(int $adminUserId, array $filters = [])
    {
        $sql = "
            SELECT p.id, p.company_id, p.title, p.price, p.active, p.created_at,
                   GROUP_CONCAT(c.title) AS categories
            FROM product p
            INNER JOIN product_category pc ON pc.product_id = p.id
            INNER JOIN category c ON c.id = pc.cat_id
            WHERE p.company_id = :adminUserId
        ";
    
        $params = ['adminUserId' => $adminUserId];
    
        if (isset($filters['active'])) {
            $sql .= " AND p.active = :active";
            $params['active'] = (int) $filters['active'];
        }
    
        if (!empty($filters['category'])) {
            $sql .= " AND c.title = :category";
            $params['category'] = $filters['category'];
        }
    
        $sql .= " GROUP BY p.id";
    
        if (!empty($filters['orderBy']) && strtolower($filters['orderBy']) === 'desc') {
            $sql .= " ORDER BY p.created_at DESC";
        } else {
            $sql .= " ORDER BY p.created_at ASC";
        }
    
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($p) => Product::hydrateByFetch($p), $products);
    }
    

    public function getOne($id)
    {
        $stm = $this->pdo->prepare("
            SELECT p.*, GROUP_CONCAT(c.title) AS categories
            FROM product p
            LEFT JOIN product_category pc ON p.id = pc.product_id
            LEFT JOIN category c ON pc.cat_id = c.id
            WHERE p.id = :id
            GROUP BY p.id
        ");
        $stm->bindParam(':id', $id, PDO::PARAM_INT);
        $stm->execute();
        
        return $stm->fetch(PDO::FETCH_ASSOC);
    } 
    

    public function insertOne($body, $adminUserId)
    {
        $stm = $this->pdo->prepare("
            INSERT INTO product (
                company_id,
                title,
                price,
                active
            ) VALUES (
                {$body['company_id']},
                '{$body['title']}',
                {$body['price']},
                {$body['active']}
            )
        ");
        if (! $stm->execute()) {
            return false;
        }

        $productId = $this->pdo->lastInsertId();

        $stm = $this->pdo->prepare("
            INSERT INTO product_category (
                product_id,
                cat_id
            ) VALUES (
                {$productId},
                {$body['category_id']}
            );
        ");
        if (! $stm->execute()) {
            return false;
        }

        $stm = $this->pdo->prepare("
            INSERT INTO product_log (
                product_id,
                admin_user_id,
                `action`
            ) VALUES (
                {$productId},
                {$adminUserId},
                'create'
            )
        ");

        return $stm->execute();
    }

    public function updateOne($id, $body, $adminUserId)
{
    $stm = $this->pdo->prepare("
        UPDATE product
        SET company_id = {$body['company_id']},
            title = '{$body['title']}',
            price = {$body['price']},
            active = {$body['active']}
        WHERE id = {$id}
    ");
    if (!$stm->execute()) {
        return false;
    }

    $stm = $this->pdo->prepare("
        UPDATE product_category
        SET cat_id = {$body['category_id']}
        WHERE product_id = {$id}
    ");
    if (!$stm->execute()) {
        return false;
    }

    $stm = $this->pdo->prepare("
        INSERT INTO product_log (
            product_id,
            admin_user_id,
            `action`,
            created_at
        ) VALUES (
            {$id},
            {$adminUserId},
            'update',
            NOW()
        )
    ");
    return $stm->execute();
}


public function deleteOne($id, $adminUserId)
{
    $stm = $this->pdo->prepare("
        DELETE FROM product_category WHERE product_id = {$id}
    ");
    if (!$stm->execute()) {
        return false;
    }

    $stm = $this->pdo->prepare("DELETE FROM product WHERE id = {$id}");
    if (!$stm->execute()) {
        return false;
    }

    $stm = $this->pdo->prepare("
        INSERT INTO product_log (
            product_id,
            admin_user_id,
            `action`,
            created_at
        ) VALUES (
            {$id},
            {$adminUserId},
            'delete',
            NOW()
        )
    ");
    return $stm->execute();
}

public function getLog($id)
{
    $stm = $this->pdo->prepare("
        SELECT product_log.*, admin_user.name as user_name
        FROM product_log
        LEFT JOIN admin_user ON admin_user.id = product_log.admin_user_id
        WHERE product_log.product_id = :id
    ");
    $stm->bindParam(':id', $id, PDO::PARAM_INT);
    $stm->execute();

    return $stm;
}

public function getLastPriceChange($productId)
{
    $stm = $this->pdo->prepare("
        SELECT au.name AS user_name, pl.timestamp
        FROM product_log pl
        LEFT JOIN admin_user au ON au.id = pl.admin_user_id
        WHERE pl.product_id = :product_id AND pl.action = 'update'
        ORDER BY pl.timestamp DESC LIMIT 1
    ");
    $stm->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $stm->execute();

    return $stm->fetch(PDO::FETCH_OBJ); 
}

}
