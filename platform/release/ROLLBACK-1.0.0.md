# Rollback — Initial Installation

v1.0.0 is the first Platform baseline. Before production installation, take a database backup and application-file snapshot.

For a failed first installation:

1. Remove/rename the Platform application files.
2. Restore/drop only the dedicated Digiti Platform database; never alter City ePaper's database as part of Platform rollback.
3. Remove `storage/install.lock` only when intentionally retrying an incomplete installation.
4. Do not reuse service tokens that may have been exposed during a failed install; rotate them from Platform Admin.

Product applications remain independently deployable and should continue working with their last verified local configuration if their Platform adapter has not yet been enabled.
