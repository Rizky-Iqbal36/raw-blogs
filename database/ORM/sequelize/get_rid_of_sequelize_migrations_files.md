# Get Rid of Sequelize Migration Files

I once join a team that using postgres and sequelize as their ORM, and they have dozens of migration files, when i try to migrate those files, i got a lot of error that saying the same problem coming from migration files.

some error like:

```bash
parent: error: column "column_name" of relation "table_name" does not exist
```

If i try to fix it one by one it will be a lot of work and time consuming, and if in the future we dont maintenance the migration files regurarly, the same issues will come.

Their solution was giving onboarding member dump files for each databases that used on this project from full timer developer. one of the dump files size was around 900 Mb and when i try to restore the dump files, the data that it contain took around 8 Gb of my SSD. I personally doesnt want my storage get decreased by the large volume of the data from dump files, moreover the process of restoring these files can be time consuming.

As another solution, i make a workaround flow to overcome this issue:

- Assume that there is dump folder contains database structure (tables only)
- Execute a command to restore the dump file to the database
- Execute a command to update the dump file.
- If migrate or undo from sequelize then do point 3

This is the solution flowchart:

```mermaid
flowchart
    subgraph Workaround Database Migration
        DB[(Database)]
        subgraph Dump Folder
            DumpA["Dump File a.sql"]
            DumpB["Dump File b.sql"]
        end
        Developer --> Command
        Command --> |Restore| DumpA --> |to| DB
        Command --> |Update| DumpB --> |from| DB
        Command --> |migrate/migrate:undo| Sequelize
        Sequelize --> DB
        Sequelize --> |Trigger Update| Command
    end
```

With this, we can overcome the issue:

- Error prone migration process: this solution provides alternative method that is less prone to errors.
- Cumbersome manual fixes: as stated, fixing errors on each migration files individually would take a lot of work and time consuming. This solution reduces the efforts to fix each migration files.
- Lack of maintenance: This flow providing a mechanism for updating the database structure from and to a dump file.
- Quickly get started: With a single command, we can get the project set up with a new blank database, avoiding the need to restore large dump files and saving significant storage space on our systems.

> **_NOTE:_**
> This is just experimenting from my side, and i think there is maybe a better way to handle this problem. Please let me know what do you think about this solution, like: is there any cons on this solution?, how we can improve it?, etc.

Now that we got the idea, how do we implement it with codes? keep reading!

## Installation

First thing first we need to install postgresql, make sure its up and running you can also use docker but in this blog i will use postgresql v14 installed using brew.

Now we need to install sequelize, sequlize cli and postgres driver using npm.

```bash
npm i sequelize pg && npm i -D sequelize-cli
```

After installing sequelize now we need to create empty sequelize project using sequelize-cli

```bash
npx sequelize-cli init
```

This will create following folders

1. config, contains config file, which tells CLI how to connect with database
2. models, contains all models for your project
3. migrations, contains all migration files
4. seeders, contains all seed files

Also dont forget to tell sequelize how to connect to database by adjusting `config/config.json`.

Lastly we need a folder to store our dump files, lets name it `dump`.

Now we are ready to create our first migration. Let's create a model named `User`:

```bash
npx sequelize-cli model:generate --name User --attributes firstName:string,lastName:string,email:string
```

## Implementation

We are ready for the implementation, let's create shell script named `make` and preparing our commands. It will look like this:

```bash
#!/bin/sh
case ${1} in
  "db:dump:update")
  ;;

  "db:dump:restore")
  ;;

  "db:migrate")
  ;;

  "db:migrate:undo")
  ;;

  "db:migrate:undo:all")
  ;;
```

Now our script can handle the commands we need. Let's build the logic one by one:

### db:dump:update

This command will create dump file from a database

```bash
#!/bin/sh
case ${1} in
  "db:dump:update")
    if test ! -z "${2}";then # Check second argument which will be used as `database_name`
      pg_dump -U postgres -d "${2}" -s -f "dump/pg-${2}-tables.sql"; # Tell postgres to create dump file from a database (tables only)
      {
        echo "CREATE DATABASE ${2} WITH TABLESPACE = pg_default;";
        echo '\\c' "${2};";
        cat "dump/pg-${2}-tables.sql";
      } > temp && mv temp "dump/pg-${2}-tables.sql"; # Concat value of the echos to our dump file (Create database and connect to the created database)
      echo "[!]Bash: UPDATE DUMP ${2} COMPLETED";
    else
      echo "[!]Bash: This command must give parameter. usage ${0} ${1} 'database_name'"
    fi
  ;;
  ...
  ...
```

### db:dump:restore

This command will handle database restoration from a dump file

```bash
#!/bin/sh
case ${1} in
  ...
  "db:dump:restore")
    if test ! -z "${2}";then # Check second argument which will be used as `database_name`
      psql -U postgres -a -f "dump/pg-${2}-tables.sql"; # Tell postgres to restore this dump file
      echo "[!]Bash: RESTORE DUMP ${2} COMPLETED";
    else
      echo "[!]Make: This command must give parameter. usage ${0} ${1} 'database_name'"
    fi
  ;;
  ...
```

### db:migrate

Handle flow sequelize migration command

```bash
#!/bin/sh
case ${1} in
  ...
  "db:migrate")
    if test ! -z "${2}";then
      if [[ ! -e "dump/pg-${2}-tables.sql" ]]; then # Check dump file if not exist then create one
        touch dump/pg-${2}-tables.sql
      fi
      if psql -lqt | cut -d \| -f 1 | grep ${2}; then # Check is database exist if not then create one
        echo "[!]Bash: Database ${2} exists"
      else
        echo "[!]Bash: Database ${2} doesnt exist";
        createdb -U postgres ${2}
        echo "[!]Bash: Database ${2} created"
      fi
      sequelize-cli db:migrate --debug --url "postgres://postgres:postgres@localhost:5432/${2}" # Sequelize command to migrate migration files into the database
      npm run db:dump:update ${2} # Update dump file from database
    else
      echo "[!]Bash: This command must give ENV parameter. usage: ${0} ${1} 'database_name'";
    fi
  ;;
  ...
```

### db:migrate:undo

Handle flow sequelize undo migration command

```bash
#!/bin/sh
case ${1} in
  ...
  "db:migrate:undo")
    if test ! -z "${2}";then
      sequelize-cli db:migrate:undo --debug --url "postgres://postgres:postgres@localhost:5432/${2}" # Sequelize undo migration
      npm run db:dump:update ${2} # Update dump file from database
    else
      echo "[!]Bash: This command must give ENV parameter. usage: ${0} ${1} 'database_name'";
    fi
  ;;
  ...
```

### db:migrate:undo:all

Handle flow sequelize undo migration command

```bash
#!/bin/sh
case ${1} in
  ...
  "db:migrate:undo:all")
    if test ! -z "${2}";then
      sequelize-cli db:migrate:undo:all --debug --url "postgres://postgres:postgres@localhost:5432/${2}" # Sequelize undo all migration
      npm run db:dump:update ${2} # Update dump file from database
    else
      echo "[!]Bash: This command must give ENV parameter. usage: ${0} ${1} 'database_name'";
    fi
  ;;
  ...
```

## Final Touch

for the final touch we can store our bash script commands to `package.json`:

```json
{
    ...
    "scripts": {
        "db:migrate": "./make db:migrate",
        "db:migrate:undo": "./make db:migrate:undo",
        "db:migrate:undo:all": "./make db:migrate:undo:all",
        "db:dump:update": "./make db:dump:update",
        "db:dump:restore": "./make db:dump:restore"
    },
    ...
}
```

Now we can use the commands using npm.

### Execute Commands

All settled up, let's migrate our `User` model we create earlier on installation section to a database named `test`, by execute:

```bash
npm run db:migrate test
```

Output:

1. Database

   ![sequelize_migrate](../../../assets/screenshots/sequelize_migrate.png)

2. Shell Output

   ![sequelize_migrate](../../../assets/screenshots/sequelize_migrate_shell_output.png)

3. Dump file

   ![sequelize_migrate](../../../assets/screenshots/sequelize_migrate_dump_file.png)

Every time you want to set a project with blank new database, you can restore the dump file, by execute:

```bash
npm run db:dump:restore test
```

Let's test the migration undo command, by execute:

```bash
npm run db:migrate:undo test
```

Output:

1. Database

   ![sequelize_migrate](../../../assets/screenshots/sequelize_undo.png)

2. Shell Output

   ![sequelize_migrate](../../../assets/screenshots/sequelize_undo_shell_output.png)

3. Dump file

   ![sequelize_migrate](../../../assets/screenshots/sequelize_undo_dump_file.png)

That's it, you can access full code [here](../../../make), i hope this information helps you in some way. Lastly i want to know how your team handle this issue? please share your experience on the comment section.
