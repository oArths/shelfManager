import sqlite3
from pathlib import Path

DB_PATH = "./data/banco/database.db"
SCHEMA_PATH = "./data/banco/banco01.sql"


def init_db():

    Path("db").mkdir(exist_ok=True)

    conn = sqlite3.connect(DB_PATH)
    cur = conn.cursor()

    with open(SCHEMA_PATH, "r",encoding="utf-8") as f:
        schema = f.read()
        cur.executescript(schema)

    conn.commit()
    conn.close()

init_db()