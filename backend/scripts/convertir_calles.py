#!/usr/bin/env python3
"""Convierte MALVINAS ARGENTINAS CALLES.xlsx en backend/data/calles.csv (calle;codigo)."""

import csv
import os
import sys

import openpyxl

RAIZ = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
XLSX = os.path.join(RAIZ, "MALVINAS ARGENTINAS CALLES.xlsx")
SALIDA = os.path.join(os.path.dirname(__file__), "..", "data", "calles.csv")


def main() -> int:
    if not os.path.isfile(XLSX):
        print(f"No existe {XLSX}", file=sys.stderr)
        return 1

    wb = openpyxl.load_workbook(XLSX, read_only=True, data_only=True)
    ws = wb.active
    filas = []
    for fila in ws.iter_rows(values_only=True):
        if not fila or fila[0] is None:
            continue
        nombre = str(fila[1] or "").strip()
        codigo = str(fila[2] or "").strip() if len(fila) > 2 else ""
        if not nombre:
            continue
        filas.append((nombre.upper(), codigo))

    os.makedirs(os.path.dirname(SALIDA), exist_ok=True)
    with open(SALIDA, "w", newline="", encoding="utf-8") as f:
        escritor = csv.writer(f, delimiter=";")
        escritor.writerow(["calle", "codigo"])
        escritor.writerows(filas)

    print(f"OK: {len(filas)} calles -> {os.path.relpath(SALIDA, RAIZ)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
