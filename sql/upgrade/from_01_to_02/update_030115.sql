-- Remove the old table
DROP TABLE IF EXISTS tickets;
CREATE TABLE tickets (
  ticket_id INTEGER unsigned AUTO_INCREMENT PRIMARY KEY,
  -- User ID who created the ticket, if any
   uid integer unsigned REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,

   -- IP address of the user who created the ticket
   ip_addr VARCHAR (45),

   -- Time of the ticket
   ticket_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;