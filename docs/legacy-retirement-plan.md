# Roadmap de Retiro Legacy (DROP TABLE) - SISPO Proyecto Normalización

Este documento establece los pasos formales, el plan de contingencia y los requisitos estrictos antes de proceder con la eliminación física (DROP) de las tablas legacy del proyecto SISPO.

---

## 1. Tablas y Estructuras a Eliminar
Las siguientes estructuras están programadas para ser retiradas del esquema de base de datos una vez que se complete satisfactoriamente el periodo de monitoreo:

### Fase 1 (Eliminación de Tablas Pivot y Archivos)
*   `postulante_meritos` (Tabla)
*   `merito_archivos` (Tabla)

### Fase 2 (Limpieza de Dependencias)
*   `tipos_documento` (Tabla - Solo cuando las vistas dinámicas ya no dependan de ella, si aplica).
*   Columnas `source_merito_id` en las 7 tablas normalizadas (`formaciones_academicas`, `formaciones_postgrado`, `experiencias_profesionales`, `experiencias_docencia`, `capacitaciones`, `producciones_intelectuales`, `reconocimientos`).

---

## 2. Condiciones Estrictas para Proceder con DROP (Checklist)
Antes de ejecutar cualquier comando `DROP`, las siguientes condiciones deben cumplirse de manera obligatoria:

- [ ] **Periodo de Monitoreo:** Haber transcurrido un mínimo de **72 horas a 2 semanas** desde el despliegue del feature flag de desconexión.
- [ ] **Cero Escrituras Legacy:** El comando `php artisan sispo:monitor-legacy-writes` debe reportar continuamente `0` escrituras nuevas post-corte.
- [ ] **Cero Fallbacks de Lectura:** No debe existir ningún log con el tag `LEGACY_FALLBACK_TRIGGERED` en `storage/logs/laravel.log`. Esto asegura que el Score Engine y la API no están leyendo los pivotes antiguos.
- [ ] **Aprobación de Readiness:** El comando `php artisan sispo:legacy-retirement-readiness` debe reportar el estado `🟢 READY_FOR_DROP`.

---

## 3. Plan de Contingencia y Backup Obligatorio
El paso de eliminación es irreversible. Se deben ejecutar los siguientes pasos de protección de datos:

1.  **Dump de Seguridad:**
    Crear un dump SQL físico de las tablas legacy como cold-storage antes de destruirlas.
    ```bash
    mysqldump -u [usuario] -p [base_de_datos] postulante_meritos merito_archivos > backup_legacy_meritos_$(date +%F).sql
    ```
2.  **Verificar Tamaño:** Validar que el dump no esté vacío.
3.  **Subir a Cold Storage:** Almacenar el `.sql` en un bucket seguro (S3, GCS) por un mínimo de 1 año como requerimiento de auditoría y QA histórico.

---

## 4. Ejecución del DROP (Comandos)
Se creará una nueva migración en Laravel para ejecutar el DROP de manera controlada y versionada.

```bash
php artisan make:migration drop_legacy_meritos_tables
```

**Contenido de la migración (Fase 1):**
```php
public function up()
{
    Schema::dropIfExists('merito_archivos');
    Schema::dropIfExists('postulante_meritos');
}

public function down()
{
    // No implementar reverse logic sin restaurar desde el dump de backup.
    throw new \Exception('No se puede revertir la eliminación legacy. Restaure desde el backup SQL manual.');
}
```

---

## 5. Riesgos Identificados
*   **Falla en Exportación PDF Antiguos:** Si un expediente antiguo (generado antes de 2026) depende de la relación estricta de `merito_archivos` y no fue migrado exitosamente, la lectura de su archivo fallará (generará un HTTP 404/500). Esto se mitiga con el hecho de que el comando de migración reportó 0 fugas de archivos.
*   **Pérdida de Información:** No recuperable. Mitigado por el Backup SQL (Punto 3).

## 6. Siguientes Pasos
Una vez ejecutado el DROP, se procederá a crear una segunda migración para limpiar los campos temporales (`source_merito_id`) de las 7 tablas normalizadas, dejando un esquema 100% puro y definitivo.
