# Operations index

## Current security and release preparation

- [Security cutover runbook](../security/SECURITY-CUTOVER-RUNBOOK.md)
- [Cutover acceptance checklist](../security/SECURITY-CUTOVER-ATTESTATION.md)
- [App-key dependency inventory](../security/APP-KEY-ROTATION-INVENTORY.md)
- [Authenticated staging acceptance](../testing/STAGING-ACCEPTANCE-HARNESS-2026-10-17.md)
- [Multilayer security audit](../audits/MULTILAYER-SECURITY-PREPOST-2026-10-17.md)

- [Git runtime-storage migration safety and release gate](STORAGE-GIT-CLEANUP-2026-10-17.md)\n\n## Legacy operator instructions (pinned, original paths)

- [Cloudways deployment](../../DEPLOY-CLOUDWAYS.md)
- [Deployment runbook](../../DEPLOYMENT-RUNBOOK-2.0.0.md)
- [Backup and restore checklist](../../BACKUP-RESTORE-CHECKLIST-2.0.0.md)
- [Disaster recovery](../../DISASTER-RECOVERY-2.0.0.md)
- [RabbitMQ and Supervisor](../../RABBITMQ-SUPERVISOR-SETUP.md)
- [Platform setup](../../PLATFORM-SETUP.md)

**Release rule:** Source CI passing is necessary but insufficient. Authenticated staging acceptance and Cloudways verified isolated restore are required before rotating/revoking production secrets, rewriting Git history or deploying. Do not include credentials in this documentation.
