import bcrypt
import sqlite3
from pathlib import Path
from fastapi import FastAPI
from pydantic import BaseModel

app = FastAPI()
DB_PATH = "./db/database.db"
SCHEMA_PATH = "./db/schema.sql"

class UserCreate(BaseModel):
    user: str
    password: str
    isAdmin: bool = False

def init_db():

    Path("db").mkdir(exist_ok=True)

    conn = sqlite3.connect(DB_PATH)
    cur = conn.cursor()

    with open(SCHEMA_PATH, "r",encoding="utf-8") as f:
        schema = f.read()
        cur.executescript(schema)

    conn.commit()
    conn.close()

def hash_password(password: str) -> bytes:
    salt = bcrypt.gensalt()
    return bcrypt.hashpw(password.encode(), salt).decode()

def verify_password(password: str, password_hash: bytes) -> bool:
   return bcrypt.checkpw(
        password.encode(),
        password_hash.encode()
    )

def createAdmin():
        
    hased = hash_password("123456") 
    conn = sqlite3.connect(DB_PATH)
    cur = conn.cursor()

    cur.execute("INSERT INTO users (username, password_hash, isAdmin) values (?,?,?)", ("admin", hased, True))
    conn.commit()
    conn.close()

init_db()
createAdmin()
