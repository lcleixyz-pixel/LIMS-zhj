START TRANSACTION;

UPDATE qms_sources
SET source_code = CASE source_code
    WHEN 'CNAS-CL01' THEN 'CNAS-CL01:2018'
    WHEN 'CNAS-CL01-G001' THEN 'CNAS-CL01-G001:2024'
    WHEN 'GB/T 27025' THEN 'GB/T 27025-2019'
    WHEN 'CMA-2023' THEN '市场监管总局公告2023年第21号'
    ELSE source_code
  END,
  version = CASE source_code
    WHEN 'CNAS-CL01' THEN '2018（2019-02-20第一次修订）'
    WHEN 'CNAS-CL01-G001' THEN '2024'
    WHEN 'GB/T 27025' THEN '2019'
    WHEN 'CMA-2023' THEN '2023年第21号公告'
    ELSE version
  END,
  effective_date = CASE source_code
    WHEN 'CNAS-CL01' THEN '2018-09-01'
    WHEN 'CNAS-CL01-G001' THEN '2024-07-01'
    WHEN 'GB/T 27025' THEN '2020-07-01'
    WHEN 'CMA-2023' THEN '2023-12-01'
    ELSE effective_date
  END,
  modified = NOW()
WHERE source_code IN ('CNAS-CL01','CNAS-CL01-G001','GB/T 27025','CMA-2023');

UPDATE qms_element_clause_mappings
SET source_code = CASE source_code
    WHEN 'CNAS-CL01' THEN 'CNAS-CL01:2018'
    WHEN 'CNAS-CL01-G001' THEN 'CNAS-CL01-G001:2024'
    WHEN 'GB/T 27025' THEN 'GB/T 27025-2019'
    WHEN 'CMA-2023' THEN '市场监管总局公告2023年第21号'
    ELSE source_code
  END,
  modified = NOW()
WHERE source_code IN ('CNAS-CL01','CNAS-CL01-G001','GB/T 27025','CMA-2023');

UPDATE qms_import_candidates
SET source_code = CASE source_code
    WHEN 'CNAS-CL01' THEN 'CNAS-CL01:2018'
    WHEN 'CNAS-CL01-G001' THEN 'CNAS-CL01-G001:2024'
    WHEN 'GB/T 27025' THEN 'GB/T 27025-2019'
    WHEN 'CMA-2023' THEN '市场监管总局公告2023年第21号'
    ELSE source_code
  END,
  payload = REPLACE(REPLACE(REPLACE(REPLACE(payload,
    '"source_code":"CNAS-CL01"', '"source_code":"CNAS-CL01:2018"'),
    '"source_code":"CNAS-CL01-G001"', '"source_code":"CNAS-CL01-G001:2024"'),
    '"source_code":"GB/T 27025"', '"source_code":"GB/T 27025-2019"'),
    '"source_code":"CMA-2023"', '"source_code":"市场监管总局公告2023年第21号"'),
  modified = NOW()
WHERE source_code IN ('CNAS-CL01','CNAS-CL01-G001','GB/T 27025','CMA-2023')
   OR payload LIKE '%"source_code":"CNAS-CL01"%'
   OR payload LIKE '%"source_code":"CNAS-CL01-G001"%'
   OR payload LIKE '%"source_code":"GB/T 27025"%'
   OR payload LIKE '%"source_code":"CMA-2023"%';

COMMIT;
