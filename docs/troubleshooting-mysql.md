# MySQL Troubleshooting

If the `db` container fails to start with errors such as `Cannot create redo log files because data files are corrupt`, the MySQL data volume is likely in a bad state. Follow these steps to reset it:

1. Stop the stack:
   ```bash
   docker compose down
   ```
2. Remove the `db_data` volume (this wipes any data stored in MySQL):
   ```bash
   docker volume rm river_db_data
   ```
   If the command warns that the volume is in use, ensure all containers are stopped and retry.
3. Start the stack again so MySQL re-initializes cleanly:
   ```bash
   docker compose up --build
   ```
4. Wait for the database to report `ready for connections` in the logs:
   ```bash
   docker compose logs -f db
   ```

## Verifying the database

Once the container is healthy you can confirm connectivity:

```bash
docker compose exec db mysql -u river -p river -e "SHOW DATABASES;"
```

The API will automatically create the `users` table the first time a Google login succeeds, so no manual migration is required.
