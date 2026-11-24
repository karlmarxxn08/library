CREATE DATABASE IF NOT EXISTS library_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE library_db;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','librarian','member') NOT NULL DEFAULT 'member',
  phone VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE books (
  id INT AUTO_INCREMENT PRIMARY KEY,
  isbn VARCHAR(20) NULL,
  title VARCHAR(255) NOT NULL,
  author VARCHAR(160) NOT NULL,
  publisher VARCHAR(160) NULL,
  year SMALLINT NULL,
  category_id INT NULL,
  shelf VARCHAR(40) NULL,
  description TEXT NULL,
  copies_total INT NOT NULL DEFAULT 1,
  copies_available INT NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX (title), INDEX (author), INDEX (isbn)
) ENGINE=InnoDB;

CREATE TABLE loans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  user_id INT NOT NULL,
  issued_at DATE NOT NULL,
  due_date DATE NOT NULL,
  returned_at DATE NULL,
  renewals TINYINT NOT NULL DEFAULT 0,
  fine DECIMAL(8,2) NOT NULL DEFAULT 0,
  fine_paid TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (book_id) REFERENCES books(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  user_id INT NOT NULL,
  status ENUM('waiting','fulfilled','cancelled') NOT NULL DEFAULT 'waiting',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- CMS: pages (shown in the top menu) and announcements (shown on the catalog home)
CREATE TABLE pages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(120) NOT NULL UNIQUE,
  title VARCHAR(200) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  type ENUM('page','announcement') NOT NULL DEFAULT 'page',
  published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE settings (k VARCHAR(60) PRIMARY KEY, v VARCHAR(255) NOT NULL) ENGINE=InnoDB;

INSERT INTO settings (k,v) VALUES ('library_name','Town Library'),('loan_days','14'),('fine_per_day','1.00'),('max_loans','5');
INSERT INTO categories (name) VALUES ('Fiction'),('Non-fiction'),('Science'),('History'),('Children');
INSERT INTO pages (slug,title,body,type) VALUES
 ('about','About us','<p>Welcome to our library. Edit this page from the CMS.</p>','page'),
 ('rules','Borrowing rules','<ul><li>Loans last 14 days and can be renewed twice.</li><li>Late returns are fined per day.</li></ul>','page'),
 ('welcome','We are open','<p>Browse the catalog, reserve titles that are out, and collect them at the desk.</p>','announcement');
