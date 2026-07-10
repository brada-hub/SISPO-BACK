# Plan de Despliegue de Producción (Release Ready Hardening)
## Proyecto: SISPO IA — Deterministic ATS Engine (No-AI Mode)

Este documento detalla el procedimiento operativo estándar para el release seguro de **SISPO IA** en su versión determinística libre de IA.

---

## 1. PRE-DEPLOYMENT BACKUP PROTOCOL

Antes de iniciar cualquier actualización en los servidores de producción, se DEBE respaldar la base de datos y el storage de documentos:

### A. Backup de MySQL Database
Ejecutar el volcado de base de datos con timestamps:
```bash
mysqldump -u [usuario] -p[password] --single-transaction --routines --triggers sispo_db > /backups/sispo_pre_deploy_$(date +%F_%H%M%S).sql
```
> [!IMPORTANT]
> El flag `--single-transaction` garantiza un backup consistente sin bloquear las lecturas de los postulantes activos en el portal.

### B. Backup de Storage de Documentos
Respaldar los PDFs de hojas de vida, fotos de perfil y adjuntos de méritos:
```bash
tar -czvf /backups/sispo_storage_backup_$(date +%F).tar.gz c:/TH/SISPO/sispo-back/storage/app/public/
```

---

## 2. CONFIGURACIÓN DE VARIABLES DE ENTORNO (.env)

El archivo `.env` de producción debe contar de forma obligatoria con las siguientes llaves configuradas:

```ini
# Desactivación total de red externa e IA para el Score Engine
SISPO_USE_GEMINI=false
GEMINI_API_KEY=null

# Cola de procesamiento en base de datos local
QUEUE_CONNECTION=database

# Entorno e inhabilitación de logs depurativos en producción
APP_ENV=production
APP_DEBUG=false
```

---

## 3. COMANDOS DE DESPLIEGUE POST-DEPLOY (Laravel Pipeline)

Una vez actualizado el código en el servidor web de UNITEPC, ejecute en orden estricto la siguiente secuencia de comandos:

### A. Migraciones y Limpieza de Caché
```bash
# Forzar ejecución de migraciones estructurales
php artisan migrate --force

# Limpieza absoluta de cachés de configuración y rutas compiladas
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Compilar configuraciones en caché rápida de producción
php artisan config:cache
php artisan route:cache
```

### B. Semillas de Perfiles de Puntuación & Catálogo Semántico
```bash
# Carga de alias, catalogos de carreras y cargos
php artisan db:seed --class=SemanticCatalogSeeder --force

# Carga de perfiles y baremos matematicos por cargo
php artisan db:seed --class=ScoreProfileSeeder --force
```

### C. Secuencia de Recálculo y Normalización Determinística
Ejecutar los procesadores cronológicos en orden de precedencia relacional:
```bash
# 1. Normalización semántica de cargos e historial
php artisan sispo:normalize-semantic --write

# 2. Procesar duraciones y vigencia laboral
php artisan sispo:process-temporal-data --write

# 3. Calcular meses de experiencia acumulados y mitigar solapamientos
php artisan sispo:calculate-experience-summaries --write

# 4. Procesar capacitaciones y ponderación de recencia por año
php artisan sispo:process-training-temporal --write

# 5. Ejecutar evaluación determinística general sobre los 308 postulantes
php artisan sispo:evaluate-deterministic --write --force-recalculate

# 6. Auditar la estratificación de riesgos y modo No-IA
php artisan sispo:audit-no-ai-mode
php artisan sispo:audit-risk-stratification
```

---

## 4. VERIFICACIÓN DE RELEASE (Release Smoke Verification)

Una vez completado el recálculo relacional, ejecute el comando auditor de release para validar que no existan errores estructurales o de consistencia:
```bash
php artisan sispo:release-check
```
> Si la salida del comando devuelve `ESTADO: APROBADO (GO)`, el sistema está apto para operación de UNITEPC.

---

## 5. PLAN DE CONTINGENCIA (Rollback Protocol)

En caso de que se detecten anomalías críticas operativas, ejecute los siguientes pasos en orden descendente:

1. **Restaurar Código Previo**:
   ```bash
   git checkout tags/v_prev_stable
   ```
2. **Restaurar Base de Datos**:
   Cargar el volcado de respaldo realizado en el paso 1-A:
   ```bash
   mysql -u [usuario] -p[password] sispo_db < /backups/sispo_pre_deploy_[timestamp].sql
   ```
3. **Limpiar Caché y Re-compilar**:
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```
4. **Verificar Portal**: Confirmar que el portal de postulaciones responde de forma regular con el esquema estable previo.
