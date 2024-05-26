#!/bin/sh

case ${1} in
  "db:migrate")
    if test ! -z "${2}";then
      if [[ ! -e "database/ORM/sequelize/dump/pg-${2}-tables.sql" ]]; then
        touch database/ORM/sequelize/dump/pg-${2}-tables.sql
      fi
      if psql -lqt | cut -d \| -f 1 | grep ${2}; then
        echo "[!]Bash: Database ${2} exists"
      else 
        echo "[!]Bash: Database ${2} doesnt exist";
        createdb -U postgres ${2}
        echo "[!]Bash: Database ${2} created"
      fi
      sequelize-cli db:migrate --debug --url "postgres://postgres:postgres@localhost:5432/${2}"
      npm run db:dump:update ${2}
    else
      echo "[!]Bash: This command must give ENV parameter. usage: ${0} ${1} 'database_name'";
    fi
  ;;

  "db:migrate:undo")
    if test ! -z "${2}";then
      sequelize-cli db:migrate:undo --debug --url "postgres://postgres:postgres@localhost:5432/${2}"
      npm run db:dump:update ${2}
    else
      echo "[!]Bash: This command must give ENV parameter. usage: ${0} ${1} 'database_name'";
    fi
  ;;

  "db:migrate:undo:all")
    if test ! -z "${2}";then
      sequelize-cli db:migrate:undo:all --debug --url "postgres://postgres:postgres@localhost:5432/${2}"
      npm run db:dump:update ${2}
    else
      echo "[!]Bash: This command must give ENV parameter. usage: ${0} ${1} 'database_name'";
    fi
  ;;

  "db:dump:restore")
    if test ! -z "${2}";then
      psql -U postgres -a -f "database/ORM/sequelize/dump/pg-${2}-tables.sql";
      echo "[!]Bash: RESTORE DUMP ${2} COMPLETED";
    else
      echo "[!]Make: This command must give parameter. usage ${0} ${1} 'database_name'"
    fi
  ;;

  "db:dump:update")
    if test ! -z "${2}";then
      pg_dump -U postgres -d "${2}" -s -f "database/ORM/sequelize/dump/pg-${2}-tables.sql";
      {
        echo "CREATE DATABASE ${2} WITH TABLESPACE = pg_default;"; 
        echo '\\c' "${2};";
        cat "database/ORM/sequelize/dump/pg-${2}-tables.sql"; 
      } > temp && mv temp "database/ORM/sequelize/dump/pg-${2}-tables.sql";
      echo "[!]Bash: UPDATE DUMP ${2} COMPLETED";
    else
      echo "[!]Bash: This command must give parameter. usage ${0} ${1} 'database_name'"
    fi
  ;;
esac
