# Installation notes

Memora expects the BASE3 system tables used by entry ACL and RBAC.

## Entry ACL tables

```text
base3system_sysuseraccess
base3system_sysgroupaccess
```

## RBAC tables

```text
base3system_sysrole
base3system_syspermission
base3system_sysrolepermission
base3system_sysuserrole
base3system_sysgrouprole
```

`base3system_sysroleaccess` is retired and should be removed from the database and schema directory.
