-- EMERGENCY ONLY：需另行批准并重新快照后执行。
ALTER TABLE customer_complaints DROP INDEX uq_complaint_company_number;
ALTER TABLE capas DROP INDEX uq_capa_company_number;
ALTER TABLE nonconformities DROP INDEX uq_nc_company_number;
ALTER TABLE capas DROP INDEX uq_capa_company_source_record;