-- Creator:       MySQL Workbench 6.1.7/ExportSQLite plugin 2013.08.05
-- Author:        Pedro Freire
-- Caption:       demos
-- Project:       browseStorage
-- Changed:       2014-09-19 12:40
-- Created:       2014-09-18 16:07
PRAGMA foreign_keys = OFF;

-- Schema: demos
--   Database schema for browseStorage demo.
--   http://www.cynergi.com/browseStorage
BEGIN;

DROP TABLE IF EXISTS "Countries";

CREATE TABLE IF NOT EXISTS "Countries"(
  "CountryID" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  "Name" TEXT NOT NULL
);
CREATE INDEX "Countries.NameIndex" ON "Countries"("Name");

DROP TABLE IF EXISTS "Currencies";

CREATE TABLE IF NOT EXISTS "Currencies"(
  "CurrencyID" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  "NameISO" TEXT NOT NULL,
  "Name" TEXT NOT NULL
);
CREATE INDEX "Currencies.NameISOIndex" ON "Currencies"("NameISO");

DROP TABLE IF EXISTS "Trips";

CREATE TABLE IF NOT EXISTS "Trips"(
  "TripID" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  "Title" TEXT NOT NULL,
  "DestinationCountryID" INTEGER NOT NULL,
  "Description" TEXT NOT NULL,
  "Price" NUMERIC NOT NULL,
  "PriceCurrencyID" INTEGER NOT NULL,
  "UntilDate" TEXT,
  CONSTRAINT "fkTripsCurrencies"
    FOREIGN KEY("PriceCurrencyID")
    REFERENCES "Currencies"("CurrencyID")
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT "fkTripsCountries"
    FOREIGN KEY("DestinationCountryID")
    REFERENCES "Countries"("CountryID")
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);
CREATE INDEX "Trips.DestinationCountryIDIndex" ON "Trips"("DestinationCountryID");
CREATE INDEX "Trips.PriceCurrencyIDIndex" ON "Trips"("PriceCurrencyID");
CREATE INDEX "Trips.UntilDateIndex" ON "Trips"("UntilDate");

DROP TABLE IF EXISTS "Clients";

CREATE TABLE IF NOT EXISTS "Clients"(
  "ClientID" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  "Name" TEXT NOT NULL,
  "Address" TEXT,
  "CountryID" INTEGER,
  "TaxNumber" TEXT,
  "ContactPhone" TEXT,
  "ContactEmail" TEXT,
  "PreferCurrencyID" INTEGER,
  CONSTRAINT "fkClientsCountries"
    FOREIGN KEY("CountryID")
    REFERENCES "Countries"("CountryID")
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT "fkClientsCurrencies"
    FOREIGN KEY("PreferCurrencyID")
    REFERENCES "Currencies"("CurrencyID")
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);
CREATE INDEX "Clients.NameIndex" ON "Clients"("Name");
CREATE INDEX "Clients.fkClientsCountriesIndex" ON "Clients"("CountryID");
CREATE INDEX "Clients.fkClientsCurrenciesIndex" ON "Clients"("PreferCurrencyID");

DROP TABLE IF EXISTS "Sales";

CREATE TABLE IF NOT EXISTS "Sales"(
  "SaleID" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  "ClientID" INTEGER NOT NULL,
  "TripID" INTEGER NOT NULL,
  "OnDate" TEXT NOT NULL,
  "Description" TEXT NOT NULL,
  "Price" NUMERIC NOT NULL,
  "PriceCurrencyID" INTEGER NOT NULL,
  CONSTRAINT "fkSalesClients"
    FOREIGN KEY("ClientID")
    REFERENCES "Clients"("ClientID")
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT "fkSalesTrips"
    FOREIGN KEY("TripID")
    REFERENCES "Trips"("TripID")
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT "fkSalesCurrencies"
    FOREIGN KEY("PriceCurrencyID")
    REFERENCES "Currencies"("CurrencyID")
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);
CREATE INDEX "Sales.ClientIDIndex" ON "Sales"("ClientID");
CREATE INDEX "Sales.TripIDIndex" ON "Sales"("TripID");
CREATE INDEX "Sales.fkSalesCurrenciesIndex" ON "Sales"("PriceCurrencyID");

COMMIT;
