-- Adicionar campo CNPJ na tabela royalties
ALTER TABLE `royalties` 
ADD COLUMN `cnpj` VARCHAR(18) NULL AFTER `estabelecimento_id`,
ADD INDEX `idx_cnpj` (`cnpj`);

-- Atualizar registros existentes com o CNPJ do estabelecimento
UPDATE royalties r
INNER JOIN estabelecimentos e ON r.estabelecimento_id = e.id
SET r.cnpj = e.document
WHERE r.cnpj IS NULL;
