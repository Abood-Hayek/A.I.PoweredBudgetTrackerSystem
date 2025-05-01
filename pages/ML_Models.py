import mysql.connector
import pandas as pd
import numpy as np
import joblib  # For saving and loading models
from sklearn.preprocessing import LabelEncoder, StandardScaler
from sklearn.linear_model import LinearRegression
from sklearn.ensemble import RandomForestRegressor
from sklearn.svm import SVR
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
import json
import os
import sys
from sqlalchemy import create_engine

user_id = sys.argv[1] 

# Step 1: Fetch data from MySQL database
def fetch_data_from_db(host, user, password, database, table_name):
    host = 'localhost'  # Your host, e.g., 'localhost'
    user = 'root'       # Your MySQL username
    password = ''       # Your MySQL password
    database = 'budgettracking'  # Your database name
    engine = create_engine(f"mysql+mysqlconnector://{user}:{password}@{host}/{database}")
    query = f"SELECT * FROM {table_name} WHERE user_id = {user_id};"
    df = pd.read_sql(query, engine)
    return df


# Step 2: Preprocess data
def preprocess_data(data):
    # Convert `date` to datetime and calculate days since the start
    data['date'] = pd.to_datetime(data['date'])
    data['days_since_start'] = (data['date'] - pd.to_datetime('2024-01-01')).dt.days

    # Encode categorical columns
    category_encoder = LabelEncoder()
    data['category_encoded'] = category_encoder.fit_transform(data['category'])

    # Filter only "expense" type rows for modeling
    expenses = data[data['type'] == 'expense']

    # Define features and target
    features = expenses[['category_encoded', 'amount', 'days_since_start']]
    target = expenses['amount']

    return data, expenses, features, target, category_encoder


# Step 3: Train and save models (one-time process)
def train_and_save_models(features, target, save_path):
    scaler = StandardScaler()
    features_scaled = scaler.fit_transform(features)

    models = {
        'Linear Regression': LinearRegression(),
        'Random Forest': RandomForestRegressor(n_estimators=200, random_state=42),
        'SVR': SVR(kernel='rbf')
    }

    # Train models
    for name, model in models.items():
        model.fit(features_scaled if name == 'SVR' else features, target)
        # Save model
        model_file = os.path.join(save_path, f"{name.replace(' ', '_')}.joblib")
        joblib.dump(model, model_file)

    # Save the scaler
    scaler_file = os.path.join(save_path, "scaler.joblib")
    joblib.dump(scaler, scaler_file)

    print("Models and scaler saved successfully.")


# Step 4: Load pre-trained models
def load_models(load_path):
    models = {}
    scaler = joblib.load(os.path.join(load_path, "scaler.joblib"))
    for model_name in ['Linear_Regression', 'Random_Forest', 'SVR']:
        model_file = os.path.join(load_path, f"{model_name}.joblib")
        models[model_name.replace('_', ' ')] = joblib.load(model_file)
    return models, scaler


# Step 5: Predict expenses for each category and generate chart data
def predict_and_generate_charts(models, scaler, data, category_encoder, save_path):
    # Calculate aggregated statistics for each category
    category_stats = data.groupby('category').agg({
        'amount': ['mean', 'sum', 'std', 'count'],
        'days_since_start': 'max'
    }).reset_index()

    # Flatten multi-index columns
    category_stats.columns = ['category', 'mean_amount', 'total_amount', 'std_amount', 'transaction_count', 'max_days_since_start']

    # Ensure no NaN in std_amount (replace with 0 if needed)
    category_stats['std_amount'] = category_stats['std_amount'].fillna(0)

    # Prepare input data for predictions
    predictions = {model_name: [] for model_name in models.keys()}
    for _, row in category_stats.iterrows():
        category_encoded = category_encoder.transform([row['category']])[0]
        input_features = np.array([[
            category_encoded,
            row['mean_amount'],
            row['total_amount']
        ]])
        input_features_scaled = scaler.transform(input_features)

        for model_name, model in models.items():
            prediction = model.predict(input_features_scaled if model_name == 'SVR' else input_features)[0]
            predictions[model_name].append({'category': row['category'], 'prediction': prediction})

    # Save predictions for each model as separate JSON files
    for model_name, model_predictions in predictions.items():
        file_name = os.path.join(save_path, f"{model_name.replace(' ', '_')}_chart.json")
        with open(file_name, 'w') as f:
            json.dump(model_predictions, f)
            

    print(f"Chart data saved to {save_path}.")


# Main execution
if __name__ == '__main__':
    while True:
        # MySQL Database credentials
        host = 'localhost'
        user = 'root'
        password = ''
        database = 'budgettracking'
        table_name = 'transactions'

        # Paths to save and load models
        model_save_path = './MLM'
        os.makedirs(model_save_path, exist_ok=True)

        # Fetch data
        data = fetch_data_from_db(host, user, password, database, table_name)

        # Preprocess data
        data, expenses, features, target, category_encoder = preprocess_data(data)

        # Check if models are already trained
        if not all(os.path.exists(os.path.join(model_save_path, f"{name}.joblib")) for name in ['Linear_Regression', 'Random_Forest', 'SVR']):
            # Train and save models if not available
            train_and_save_models(features, target, model_save_path)

        # Load pre-trained models
        models, scaler = load_models(model_save_path)

        # Predict expenses and generate charts
        predict_and_generate_charts(models, scaler, data, category_encoder, './predictions')

        # Wait for 5 seconds before the next execution
        # time.sleep(5)
