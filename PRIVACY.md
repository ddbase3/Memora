# Authorization changes in Memora

The 2026-10-19 authorization migration uses the BASE3 authority contracts and
existing native XRM user, group, role, permission and entry-ACL sources. The
plugin introduces no extra ACL storage. If active, the Manager/server RPC layer
processes authenticated user identifiers and grant metadata over the configured
XRM microservice connection. Entry ACL changes use the existing Memora entity
update transaction and are subject to native edit permissions. Native role and
permission catalog changes are restricted to the XRM `system/admin` decision.

Revocation or replacement affects active native grant or role relation rows,
not archived deployment backups or external logs. Exact data retention, audit
policies, host-only storage, network recipients and subject-rights workflows
cannot be established from the uploaded selective sources and must be verified
against the live project. This module document is not a privacy notice or a
claim of regulatory compliance. See the host root `PRIVACY.md` and
`docs/authorization-xrm-manager.md` for operator responsibilities.
