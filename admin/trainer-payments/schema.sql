-- ---------------------------------------------------------------------
-- Trainer Payments
-- ---------------------------------------------------------------------
-- Records each salary / payment made by the gym admin to a trainer.
-- Every row is a permanent history entry (never deleted by the app logic
-- that creates new rows), so the full payment history of every trainer
-- is always available.

CREATE TABLE IF NOT EXISTS trainer_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trainer_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_date DATE NOT NULL,
  pay_period VARCHAR(40) DEFAULT NULL,
  payment_mode ENUM('Cash','Bank Transfer','UPI','Cheque','Other') DEFAULT 'Cash',
  reference_no VARCHAR(80) DEFAULT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_trpay_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
  INDEX idx_trpay_trainer (trainer_id),
  INDEX idx_trpay_date (payment_date)
) ENGINE=InnoDB;
